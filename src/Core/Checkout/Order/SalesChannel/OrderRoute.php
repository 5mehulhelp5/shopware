<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class OrderRoute extends AbstractOrderRoute
{
    /**
     * @internal
     *
     */
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly OrderService $orderService
    ) {
    }

    public function getDecorated(): AbstractOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/order', name: 'store-api.order', methods: ['GET', 'POST'], defaults: ['_entity' => 'order'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        $orderResult = $this->orderService->getOrdersByCriteria($criteria, $context);
        /** @var OrderCollection $orders */
        $orders = $orderResult->getEntities();
        $deepLinkFilter = \current(array_filter($criteria->getFilters(), static fn (Filter $filter) => \in_array('order.deepLinkCode', $filter->getFields(), true)
            || \in_array('deepLinkCode', $filter->getFields(), true))) ?: null;
        // remove old orders only if there is a deeplink filter
        if ($deepLinkFilter !== null) {
            $orders = $this->filterOldOrders($orders);
        }

        // Handle guest authentication if deeplink is set
        if (!$context->getCustomer() && $deepLinkFilter instanceof EqualsFilter) {
            try {
                $cacheKey = strtolower((string) $deepLinkFilter->getValue()) . '-' . $request->getClientIp();

                $this->rateLimiter->ensureAccepted(RateLimiter::GUEST_LOGIN, $cacheKey);
            } catch (RateLimitExceededException $exception) {
                throw OrderException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }
            /** @var OrderEntity|null $order */
            $order = $orders->first();
            $this->checkGuestAuth($order, $request);
        }

        if (isset($cacheKey)) {
            $this->rateLimiter->reset(RateLimiter::GUEST_LOGIN, $cacheKey);
        }
        $response = new OrderRouteResponse($orderResult);

        foreach ($orders as $order) {
            /** @var OrderEntity $order */
            if ($this->orderService->isPaymentChangeableByTransactionState($order) || ($request->get('checkPromotion') === true && $this->orderService->isPaymentChangeableByPromotions($order, $context))) {
                $response->addPaymentChangeable(paymentChangeable: [$order->getId() => true]);
            }
        }

        return $response;
    }

    private function filterOldOrders(OrderCollection $orders): OrderCollection
    {
        // Search with deepLinkCode needs updatedAt Filter
        $latestOrderDate = (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))->modify(-abs(30) . ' Day');

        return $orders->filter(fn (OrderEntity $order) => $order->getCreatedAt() > $latestOrderDate || $order->getUpdatedAt() > $latestOrderDate);
    }

    /**
     * @param OrderEntity|null $order
     * @throws OrderException
     */
    private function checkGuestAuth(?OrderEntity $order, Request $request): void
    {
        if ($order === null) {
            throw OrderException::guestCustomerNotLoggedIn();
        }

        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            throw OrderException::customerNotLoggedIn();
        }

        $guest = $orderCustomer->getCustomer() !== null && $orderCustomer->getCustomer()->getGuest();
        // Throw exception when customer is not guest
        if (!$guest) {
            throw OrderException::guestCustomerNotLoggedIn();
        }

        // Verify email and zip code with this order
        if ($request->get('email', false) && $request->get('zipcode', false)) {
            $billingAddress = $order->getBillingAddress();
            if (
                $billingAddress === null
                || $request->get('email') !== $orderCustomer->getEmail()
                || $request->get('zipcode') !== $billingAddress->getZipcode()
            ) {
                throw OrderException::guestWrongCredentials();
            }
        } else {
            throw OrderException::guestCustomerNotLoggedIn();
        }
    }
}
