<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

#[Package('core')]
class Migration1234567890AddProductListingIdToCategory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        // Replace with current timestamp or a unique number
        return 1234567890;
    }

    public function update(Connection $connection): void
    {
        // Add the product_listing_id column to the category table
        $connection->executeStatement('
            ALTER TABLE `category`
            ADD COLUMN `product_listing_id` BINARY(16) NULL AFTER `custom_entity_type_id`,
            ADD CONSTRAINT `fk.category.product_listing_id` FOREIGN KEY (`product_listing_id`)
                REFERENCES `cms_slot` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes needed
    }
}
