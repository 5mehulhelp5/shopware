<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints\NotBlank;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopware\Core\Checkout\Order\Event\OrderCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Checkout\Order\OrderCollection;

#[Package('checkout')]
class OrderService
{
    final public const CUSTOMER_COMMENT_KEY = 'customerComment';
    final public const AFFILIATE_CODE_KEY = 'affiliateCode';
    final public const CAMPAIGN_CODE_KEY = 'campaignCode';

    final public const ALLOWED_TRANSACTION_STATES = [
        OrderTransactionStates::STATE_OPEN,
        OrderTransactionStates::STATE_CANCELLED,
        OrderTransactionStates::STATE_REMINDED,
        OrderTransactionStates::STATE_FAILED,
        OrderTransactionStates::STATE_CHARGEBACK,
        OrderTransactionStates::STATE_UNCONFIRMED,
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly DataValidationFactoryInterface $orderValidationFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartService $cartService,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly EntityRepository $promotionRepository,
        private readonly EntityRepository $orderRepository,
    ) {
    }

    /**
     * @throws ConstraintViolationException
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): string
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $this->validateOrderData($data, $context, $cart->getLineItems()->hasLineItemWithState(State::IS_DOWNLOAD));

        $this->validateCart($cart, $context->getContext());

        return $this->cartService->order($cart, $context, $data->toRequestDataBag());
    }

    /**
     * @return EntitySearchResult<OrderCollection>
     */
    public function getOrdersByCriteria(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        ReplicaConnection::ensurePrimary();

        $criteria->addFilter(new EqualsFilter('order.salesChannelId', $context->getSalesChannel()->getId()));
        $criteria->getAssociation('documents')
            ->addFilter(new EqualsFilter('config.displayInCustomerAccount', 'true'))
            ->addFilter(new EqualsFilter('sent', true));

        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('orderCustomer.customer');

        $deepLinkFilter = \current(array_filter($criteria->getFilters(), static fn (Filter $filter) => \in_array('order.deepLinkCode', $filter->getFields(), true)
            || \in_array('deepLinkCode', $filter->getFields(), true))) ?: null;

        if ($context->getCustomer()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        } elseif ($deepLinkFilter === null) {
         throw OrderException::customerNotLoggedIn();
        }

        $this->eventDispatcher->dispatch(new OrderCriteriaEvent($criteria, $context));
        /** @var EntitySearchResult<OrderCollection> $searchResult */
        $searchResult = $this->orderRepository->search($criteria, $context->getContext());
        return $searchResult;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderStateTransition(
        string $orderId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order',
                $orderId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            throw StateMachineException::stateMachineStateNotFound('order', $transition);
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderTransactionStateTransition(
        string $orderTransactionId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_transaction',
                $orderTransactionId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            throw StateMachineException::stateMachineStateNotFound('order_transaction', $transition);
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderDeliveryStateTransition(
        string $orderDeliveryId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_delivery',
                $orderDeliveryId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            throw StateMachineException::stateMachineStateNotFound('order_delivery', $transition);
        }

        return $toPlace;
    }

    public function isPaymentChangeableByTransactionState(OrderEntity $order): bool
    {
        $state = $order->getTransactions()?->last()?->getStateMachineState()?->getTechnicalName();
        if (!$state) {
            return true;
        }

        if (\in_array($state, self::ALLOWED_TRANSACTION_STATES, true)) {
            return true;
        }

        return false;
    }

    public function isPaymentChangeableByPromotions(OrderEntity $order, SalesChannelContext $context): bool
    {
        /** @var PromotionCollection $promotions */
        $promotions = $this->getActivePromotions($order, $context);

        foreach ($promotions as $promotion) {
            if (!$this->checkPromotion($promotion)) {
                return false;
            }
        }

        return true;
    }

    private function getActivePromotions(OrderEntity $order, SalesChannelContext $context): PromotionCollection
    {
        $promotionIds = [];
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $payload = $lineItem->getPayload();
            if (isset($payload['promotionId']) && \is_string($payload['promotionId'])) {
                $promotionIds[] = $payload['promotionId'];
            }
        }

        $promotions = new PromotionCollection();

        if (!empty($promotionIds)) {
            $criteria = new Criteria($promotionIds);
            $criteria->addAssociation('cartRules');
            $promotions = $this->promotionRepository->search($criteria, $context->getContext())->getEntities();
        }
        /** @var PromotionCollection $promotions */
        return $promotions;
    }

    private function checkRuleType(Container $rule): bool
    {
        foreach ($rule->getRules() as $nestedRule) {
            if ($nestedRule instanceof Container && $this->checkRuleType($nestedRule) === false) {
                return false;
            }
            if ($nestedRule instanceof PaymentMethodRule) {
                return false;
            }
        }

        return true;
    }

    private function checkPromotion(PromotionEntity $promotion): bool
    {
        if ($promotion->getCartRules() === null) {
            return true;
        }

        foreach ($promotion->getCartRules() as $cartRule) {
            if (!$this->checkCartRule($cartRule)) {
                return false;
            }
        }

        return true;
    }

    private function checkCartRule(RuleEntity $cartRule): bool
    {
        $payload = $cartRule->getPayload();
        if (!$payload instanceof Container) {
            return true;
        }

        foreach ($payload->getRules() as $rule) {
            if ($rule instanceof Container && $this->checkRuleType($rule) === false) {
                return false;
            }
        }

        return true;
    }

    private function validateCart(Cart $cart, Context $context): void
    {
        $idsOfPaymentMethods = [];

        foreach ($cart->getTransactions() as $paymentMethod) {
            $idsOfPaymentMethods[] = $paymentMethod->getPaymentMethodId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true)
        );

        $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context);

        if ($paymentMethods->getTotal() !== \count(array_unique($idsOfPaymentMethods))) {
            foreach ($cart->getTransactions() as $paymentMethod) {
                if (!\in_array($paymentMethod->getPaymentMethodId(), $paymentMethods->getIds(), true)) {
                    throw new PaymentMethodNotAvailableException($paymentMethod->getPaymentMethodId());
                }
            }
        }
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateOrderData(
        ParameterBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): void {
        $definition = $this->getOrderCreateValidationDefinition(new DataBag($data->all()), $context, $hasVirtualGoods);
        $violations = $this->dataValidator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }

    private function getOrderCreateValidationDefinition(
        DataBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): DataValidationDefinition {
        $validation = $this->orderValidationFactory->create($context);

        if ($hasVirtualGoods) {
            $validation->add('revocation', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
