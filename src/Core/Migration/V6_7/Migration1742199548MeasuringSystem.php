<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1742199548MeasuringSystem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742199548;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measuring_system` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
          ) ENGINE = InnoDB');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measuring_system_translation` (
            `name` VARCHAR(255) NULL,
            `measuring_system_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`measuring_system_id`,`language_id`),
            CONSTRAINT `fk.measuring_system_translation.measuring_system_id` FOREIGN KEY (`measuring_system_id`)
              REFERENCES `measuring_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.measuring_system_translation.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measuring_display_unit` (
              `id` BINARY(16) NOT NULL,
              `measuring_system_id` BINARY(16) NOT NULL,
              `default` TINYINT(1) DEFAULT 0 NOT NULL,
              `type` VARCHAR(20) NOT NULL,
              `short_name` VARCHAR(20) NOT NULL,
              `factor` DOUBLE NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.measuring_display_unit.measuring_system_id` FOREIGN KEY (`measuring_system_id`)
                REFERENCES `measuring_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
          ) ENGINE = InnoDB');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measuring_display_unit_translation` (
            `name` VARCHAR(255) NULL,
            `plural_name` VARCHAR(255) NULL,
            `measuring_display_unit_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`measuring_display_unit_id`,`language_id`),
            CONSTRAINT `fk.measuring_display_unit_translation.measuring_display_unit_id` FOREIGN KEY (`measuring_display_unit_id`)
              REFERENCES `measuring_display_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.measuring_display_unit_translation.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `product_measuring_display_unit` (
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `weight_unit_id` BINARY(16) NOT NULL,
              `length_unit_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`product_id`, `product_version_id`, `weight_unit_id`, `length_unit_id`),
              CONSTRAINT `fk.product_measuring_display_unit.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_measuring_display_unit.weight_unit_id` FOREIGN KEY (`weight_unit_id`)
                REFERENCES `measuring_display_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_measuring_display_unit.length_unit_id` FOREIGN KEY (`length_unit_id`)
                REFERENCES `measuring_display_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        if ($this->columnExists($connection, 'sales_channel_domain', 'measuring_system_id')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `sales_channel_domain`
            ADD COLUMN `measuring_system_id` BINARY(16) NULL,
            ADD CONSTRAINT `fk.sales_channel_domain.measuring_system_id` FOREIGN KEY (`measuring_system_id`) REFERENCES `measuring_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');
    }
}
