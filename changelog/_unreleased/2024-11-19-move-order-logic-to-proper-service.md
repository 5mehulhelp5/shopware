---
title: Move common methods to the OrderService
issue: NEXT-39705
---
# Core
* Changed `\Shopware\Core\Checkout\Order\SalesChannel\OrderService`:
    - `getOrdersByCriteria` method added in order to fetch orders according to passed criteria and current context
    - `isPaymentChangeableByPromotions` method added to check if an order can be altered in terms of payment method

* Changed `\Shopware\Core\Checkout\Order\SalesChannel\OrderRoute`:
    - use `order.service` methods instead of promotion and order repository
    - use the service to decide if order can have payment method changed

* Changed `Core/Checkout/DependencyInjection/order.xml`:
    - inject `Shopware\Core\Checkout\Order\SalesChannel\OrderService` for `OrderRoute`
    - stop using `promotion.repository` directly in the `OrderRoute` 
