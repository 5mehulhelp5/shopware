<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ScaleUnit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ScaleUnit\Extension\SalesChannelDomainExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SalesChannelDomainExtension::class)]
class SalesChannelDomainExtensionTest extends TestCase
{
    private SalesChannelDomainExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new SalesChannelDomainExtension();
    }

    public function testGetDefinitionClass(): void
    {
        static::assertSame(SalesChannelDomainDefinition::class, $this->extension->getDefinitionClass());
    }

    public function testExtendFields(): void
    {
        $expectedFieldCollection = new FieldCollection();
        $expectedFieldCollection->add(
            (new FkField('measuring_system_id', 'measuringSystemId', 'measuring_system.definition'))->addFlags(new ApiAware())
        );
        $expectedFieldCollection->add(
            new ManyToOneAssociationField('measuringSystem', 'measuring_system_id', 'measuring_system.definition', 'id')
        );

        $actualFieldCollection = new FieldCollection();
        $this->extension->extendFields($actualFieldCollection);

        static::assertEquals($expectedFieldCollection, $actualFieldCollection);
    }

    public function testGetEntityName(): void
    {
        static::assertSame(SalesChannelDomainDefinition::ENTITY_NAME, $this->extension->getEntityName());
    }
}
