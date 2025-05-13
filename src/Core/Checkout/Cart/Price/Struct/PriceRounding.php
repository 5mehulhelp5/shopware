<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;

#[Package('checkout')]
class PriceRounding
{
    public function round(
        float $unitPrice,
        float $totalPrice,
        CalculatedTaxCollection $calculatedTaxes,
        TaxRuleCollection $taxRules,
        int $quantity,
        ?ReferencePrice $referencePrice,
        ?ListPrice $listPrice,
        ?RegulationPrice $regulationPrice,
        CurrencyEntity $currency
    ): CalculatedPrice {
        return new CalculatedPrice(
            $unitPrice,
            $totalPrice,
            $calculatedTaxes,
            $taxRules,
            $quantity,
            $referencePrice,
            $listPrice,
            $regulationPrice,
            $currency
        );
    }
}