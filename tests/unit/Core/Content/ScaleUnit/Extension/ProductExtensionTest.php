<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ScaleUnit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ScaleUnit\Extension\ProductExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExtension::class)]
class ProductExtensionTest extends TestCase
{
    private ProductExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ProductExtension();
    }

    public function testGetEntityName(): void
    {
        static::assertSame(ProductDefinition::ENTITY_NAME, $this->extension->getEntityName());
    }

    public function testCorrectExtendFields(): void
    {
        $expectedFieldCollection = new FieldCollection();
        $expectedFieldCollection->add(
            (new ManyToManyAssociationField('weightUnits', 'measuring_display_unit.definition', 'product_measuring_display_unit.definition', 'product_id', 'weight_unit_id'))->addFlags(new CascadeDelete()),
        );
        $expectedFieldCollection->add(
            (new ManyToManyAssociationField('lengthUnits', 'measuring_display_unit.definition', 'product_measuring_display_unit.definition', 'product_id', 'length_unit_id'))->addFlags(new CascadeDelete()),
        );

        $actualFieldCollection = new FieldCollection();
        $this->extension->extendFields($actualFieldCollection);

        static::assertEquals($expectedFieldCollection, $actualFieldCollection);
    }
}
