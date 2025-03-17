<?php declare(strict_types=1);

namespace Shopware\Core\Content\ScaleUnit\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[Entity('measuring_system')]
class MeasuringSystemEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Field(type: FieldType::STRING, api: true)]
    public string $technicalName;

    #[Field(type: FieldType::STRING, translated: true)]
    public ?string $name = null;
}
