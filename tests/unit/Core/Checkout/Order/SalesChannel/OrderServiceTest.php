<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Event\OrderCriteriaEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\Validation\OrderValidationFactory;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(OrderService::class)]
class OrderServiceTest extends TestCase
{
    private MockObject&CartService $cartService;

    private MockObject&EntityRepository $paymentMethodRepository;

    private MockObject&EntityRepository $orderRepository;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private OrderService $orderService;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->cartService = $this->createMock(CartService::class);
        $this->paymentMethodRepository = $this->createMock(EntityRepository::class);
        $stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $promotionRepository = $this->createMock(EntityRepository::class);
        $this->orderRepository = $this->createMock(EntityRepository::class);

        $this->orderService = new OrderService(
            new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            new OrderValidationFactory(),
            $this->eventDispatcher,
            $this->cartService,
            $this->paymentMethodRepository,
            $stateMachineRegistry,
            $promotionRepository,
            $this->orderRepository
        );
    }

    public function testCreateOrderWithDigitalGoodsNeedsRevocationConfirm(): void
    {
        $dataBag = new DataBag();
        $dataBag->set('tos', true);
        $context = $this->createMock(SalesChannelContext::class);

        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'test'))->setStates([State::IS_PHYSICAL]));

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->expects(static::exactly(2))->method('order');

        $idSearchResult = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
        $this->paymentMethodRepository->method('searchIds')->willReturn($idSearchResult);

        $this->orderService->createOrder($dataBag, $context);

        $cart->add((new LineItem('b', 'test'))->setStates([State::IS_DOWNLOAD]));

        try {
            $this->orderService->createOrder($dataBag, $context);

            static::fail('Did not throw exception');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $errors = iterator_to_array($exception->getErrors());
            static::assertCount(1, $errors);
            static::assertEquals('VIOLATION::IS_BLANK_ERROR', $errors[0]['code']);
            static::assertEquals('/revocation', $errors[0]['source']['pointer']);
        }

        $dataBag->set('revocation', true);

        $this->orderService->createOrder($dataBag, $context);
    }

    public function testIsChangeableForValidState(): void
    {
        $order = new OrderEntity();
        $order->setId('1');
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setStateId(OrderTransactionStates::STATE_OPEN);
        $orderTransaction->setId('1');
        $orderTransaction->setUniqueIdentifier('1');
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setTechnicalName(OrderTransactionStates::STATE_OPEN);
        $stateMachineState->setId('1');
        $stateMachineState->setUniqueIdentifier('1');
        $orderTransaction->setStateMachineState($stateMachineState);
        $orderTransaction->setOrder($order);
        $order->setTransactions(new OrderTransactionCollection([$orderTransaction]));
        $order->setUniqueIdentifier('1');

        $isChangeable = $this->orderService->isPaymentChangeableByTransactionState($order);
        static::assertTrue($isChangeable);
    }

    public function testIsNotChangeableForInvalidState(): void
    {
        $order = new OrderEntity();
        $order->setId('1');
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setStateId(OrderTransactionStates::STATE_OPEN);
        $orderTransaction->setId('1');
        $orderTransaction->setUniqueIdentifier('1');
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setTechnicalName('NOT-ALLOWED-STATE');
        $stateMachineState->setId('1');
        $stateMachineState->setUniqueIdentifier('1');
        $orderTransaction->setStateMachineState($stateMachineState);
        $orderTransaction->setOrder($order);
        $order->setTransactions(new OrderTransactionCollection([$orderTransaction]));
        $order->setUniqueIdentifier('1');

        $isChangeable = $this->orderService->isPaymentChangeableByTransactionState($order);
        static::assertFalse($isChangeable);
    }

    public function testIsChangeableFoStateMachineStateNullishState(): void
    {
        $order = new OrderEntity();
        $order->setId('1');
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setStateId(OrderTransactionStates::STATE_OPEN);
        $orderTransaction->setId('1');
        $orderTransaction->setUniqueIdentifier('1');
        $orderTransaction->setOrder($order);
        $order->setTransactions(new OrderTransactionCollection([$orderTransaction]));
        $order->setUniqueIdentifier('1');

        $isChangeable = $this->orderService->isPaymentChangeableByTransactionState($order);
        static::assertTrue($isChangeable);
    }

    public function testGetOrdersElementsResult(): void
    {
        $criteria = new Criteria();
        $context = $this->createMock(SalesChannelContext::class);
        $coreContext = $this->createMock(Context::class);
        $context->method('getContext')->willReturn($coreContext);
        $context->method('getCustomer')->willReturn($this->createMock(CustomerEntity::class));
        $context->method('getSalesChannel')->willReturn($this->createMock(SalesChannelEntity::class));

        $order = new OrderEntity();
        $order->setId('1');
        $order->setSalesChannelId('sales-channel-id');
        $orderResult = $this->createMock(EntitySearchResult::class);
        $orderResult->method('getTotal')->willReturn(1);
        $orderResult->method('getElements')->willReturn([$order]);
        $this->orderRepository->method('search')->willReturn($orderResult);
        $ordersCollection = $this->orderService->getOrdersByCriteria($criteria, $context);
        static::assertCount(1, $ordersCollection->getElements());
        static::assertSame($order, $ordersCollection->getElements()[0]);

    }

    public function testGetOrdersByCriteriaWithCustomer(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.deepLinkCode', 'deep-link-code'));
        $context = $this->createMock(SalesChannelContext::class);
        $coreContext = $this->createMock(Context::class);
        $context->method('getContext')->willReturn($coreContext);
        $context->method('getCustomer')->willReturn($this->createMock(CustomerEntity::class));
        $context->method('getSalesChannel')->willReturn($this->createMock(SalesChannelEntity::class));

        $this->orderService->getOrdersByCriteria($criteria, context: $context);

        $associations = $criteria->getAssociations();
        static::assertArrayHasKey('documents', $associations);
        static::assertArrayHasKey('billingAddress', $associations);
        $filters = $criteria->getFilterFields();
        static::assertEquals(['order.deepLinkCode', 'order.salesChannelId', 'order.orderCustomer.customerId'], $filters);
    }

    public function testGetOrdersByCriteriaWithoutCustomerAndWithDeepLinkFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.deepLinkCode', 'deep-link-code'));
        $context = $this->createMock(SalesChannelContext::class);
        $coreContext = $this->createMock(Context::class);
        $context->method('getContext')->willReturn($coreContext);
        $context->method('getCustomer')->willReturn(null);
        $context->method('getSalesChannel')->willReturn($this->createMock(SalesChannelEntity::class));

        $this->eventDispatcher->method('dispatch')->willReturn(new OrderCriteriaEvent($criteria, $context));
        $this->eventDispatcher->expects(static::exactly(1))->method('dispatch')->with(static::isInstanceOf(OrderCriteriaEvent::class));
        $this->orderRepository->expects(static::exactly(1))->method('search')->with($criteria, $coreContext);
        $this->orderService->getOrdersByCriteria($criteria, context: $context);

        $filters = $criteria->getFilterFields();
        static::assertEquals(['order.deepLinkCode', 'order.salesChannelId'], $filters);
        static::assertNotContains(['order.orderCustomer.customerId'], $filters);
    }

    public function testGetOrdersByCriteriaWithoutCustomerAndWithoutDeepLinkFilter(): void
    {
        $criteria = new Criteria();
        $context = $this->createMock(SalesChannelContext::class);
        $this->expectException(OrderException::class);
        $this->orderService->getOrdersByCriteria($criteria, $context);
    }
}
