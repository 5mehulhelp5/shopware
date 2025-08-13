<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Log\Package;

/**
 * Marker attribute to indicate that a constraint constructor supports named arguments
 *
 * @internal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD)]
class HasNamedArguments
{
}