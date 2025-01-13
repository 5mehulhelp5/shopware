import { test } from '@fixtures/AcceptanceTest';

test('As a admin, I can restrict to use of a promotion once per customer.', {tag: '@Promotion'}, async ({
    ShopCustomer,
    IdProvider,
    TestDataService,
    StorefrontHome,
    AddProductToCart,
    Login,
    StorefrontProductDetail,
    ApplyPromotion,
}) => {


    const promotion = await TestDataService.createPromotionWithCode({ code: 'myCode', maxRedemptionsPerCustomer: 1, discounts: [{ scope: 'cart', type: 'absolute', value: 10, considerAdvancedRules: false }] });

    const customer = await TestDataService.createCustomer();
    const product = await TestDataService.createBasicProduct();

    // Scenario: Create a promotion with max uses per customer as 1 via API
    // GIVEN: A promotion with max uses 1 is available for a saleschannel
    // WHEN A customer adds a product to the cart
    // AND the customer applies the promotion code
    // THEN the promotion should be applied to the cart and the order should be placed
    // WHEN the customer places the same order again
    // THEN the promotion should not be applied to the cart


    await test.step('Add a product to the cart and apply the promotion code and verify promotion applied successfully.', async () => {

        await ShopCustomer.attemptsTo(Login(customer));

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));

        await ShopCustomer.attemptsTo(AddProductToCart(product));

        await ShopCustomer.attemptsTo(ApplyPromotion(promotion, true));

        const subtotal = product.price[0].gross - promotion.discounts[0].value;
        await ShopCustomer.expects(StorefrontProductDetail.offCanvasSummaryTotalPrice).toHaveText(new RegExp(`\\D${subtotal.toFixed(2)}\\*`));

    });


});
