<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

#[Package('inventory')]
class Migration1697535822AddCurrencyIdToProductPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697535822; // Current timestamp: 2023-10-17
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product_price` 
            ADD COLUMN `currency_id` BINARY(16) NULL AFTER `quantity_end`,
            ADD CONSTRAINT `fk.product_price.currency_id` FOREIGN KEY (`currency_id`)
                REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
