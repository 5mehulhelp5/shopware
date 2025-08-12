<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * @internal
 */
class HookableEventCollectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private HookableEventCollector $hookableEventCollector;

    protected function setUp(): void
    {
        $this->hookableEventCollector = static::getContainer()->get(HookableEventCollector::class);
    }

    public function testGetHookableEventNamesWithPrivileges(): void
    {
        $hookableEventNamesWithPrivileges = $this->hookableEventCollector->getHookableEventNamesWithPrivileges(Context::createDefaultContext());
        static::assertNotEmpty($hookableEventNamesWithPrivileges);

        foreach ($hookableEventNamesWithPrivileges as $key => $hookableEventNamesWithPrivilege) {
            static::assertIsArray($hookableEventNamesWithPrivilege);
            static::assertIsString($key);
            static::assertArrayHasKey('privileges', $hookableEventNamesWithPrivilege);
        }
    }

    public function testGetHookableEntities(): void
    {
        $hookableEntities = $this->hookableEventCollector->getHookableEntities();
        dd($hookableEntities);
        static::assertNotEmpty($hookableEntities);

        static::assertContains(ProductDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(ProductPriceDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(MediaDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(CategoryDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(CustomerDefinition::ENTITY_NAME, $hookableEntities);
    }
}
