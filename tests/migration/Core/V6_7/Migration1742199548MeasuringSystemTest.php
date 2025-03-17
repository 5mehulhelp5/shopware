<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1742199548MeasuringSystem;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1742199548MeasuringSystem::class)]
class Migration1742199548MeasuringSystemTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_domain` DROP FOREIGN KEY `fk.sales_channel_domain.measuring_system_id`');
            $this->connection->executeStatement('ALTER TABLE `sales_channel_domain` DROP COLUMN `measuring_system_id`;');
        } catch (\Throwable) {
        }

        $this->connection->executeStatement('DROP TABLE IF EXISTS `product_measuring_display_unit`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measuring_display_unit_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measuring_display_unit`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measuring_system_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measuring_system`;');
    }

    public function testMigration(): void
    {
        $sm = $this->connection->createSchemaManager();

        static::assertFalse($sm->tablesExist(['measuring_system']));
        static::assertFalse($sm->tablesExist(['measuring_system_translation']));
        static::assertFalse($sm->tablesExist(['measuring_display_unit']));
        static::assertFalse($sm->tablesExist(['measuring_display_unit_translation']));
        static::assertFalse($sm->tablesExist(['product_measuring_display_unit']));

        $migration = new Migration1742199548MeasuringSystem();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($sm->tablesExist(['measuring_system']));
        static::assertTrue($sm->tablesExist(['measuring_system_translation']));
        static::assertTrue($sm->tablesExist(['measuring_display_unit']));
        static::assertTrue($sm->tablesExist(['measuring_display_unit_translation']));
        static::assertTrue($sm->tablesExist(['product_measuring_display_unit']));
    }
}
