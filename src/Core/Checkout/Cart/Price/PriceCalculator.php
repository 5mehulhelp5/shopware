<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;

#[Package('checkout')]
class PriceCalculator
{
    public function buildPriceDefinition(
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

        $currency = new CurrencyEntity();
        $currency->setId('currency-id');
        $currency->setName('USD');
        $currency->setFactor(1.0);
        $currency->setSymbol('$');
        $currency->setShortName('USD');
        $currency->setPosition(1);
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