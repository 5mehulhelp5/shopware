<?php declare(strict_types=1);

namespace Shopware\Core\Content\ScaleUnit\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[Entity('measuring_display_unit_translation')]
class MeasuringDisplayUnitTranslationEntity extends EntityStruct
{
    #[PrimaryKey]
    #[ForeignKey(entity: 'measuring_display_unit')]
    #[Field(type: FieldType::UUID)]
    public string $measuringDisplayUnitId;

    #[PrimaryKey]
    #[ForeignKey(entity: 'language')]
    #[Field(type: FieldType::UUID)]
    public string $languageId;

    #[Field(type: FieldType::STRING)]
    public ?string $name = null;

    #[Field(type: FieldType::STRING)]
    public ?string $pluralName = null;
}
