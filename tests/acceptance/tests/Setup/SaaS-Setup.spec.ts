import { test } from '@fixtures/AcceptanceTest';

test('Setup a saas instance.', { tag: ['@SaaS', '@Setup'] }, async ({ SaaSInstanceSetup }) => {
    test.skip();
    await SaaSInstanceSetup();
});
