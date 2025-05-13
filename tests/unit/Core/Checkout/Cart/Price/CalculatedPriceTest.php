<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\Currency\CurrencyEntity;

class CalculatedPriceTest extends TestCase
{
    public function testCalculatedPriceConstructor(): void
    {
        $unitPrice = 10.0;
        $totalPrice = 100.0;
        $calculatedTaxes = $this->createMock(CalculatedTaxCollection::class);
        $taxRules = $this->createMock(TaxRuleCollection::class);
        $quantity = 10;
        $referencePrice = null;
        $listPrice = null;
        $regulationPrice = null;
        $mockCurrency = $this->createMock(CurrencyEntity::class);

        $calculatedPrice = new CalculatedPrice(
            $unitPrice,
            $totalPrice,
            $calculatedTaxes,
            $taxRules,
            $quantity,
            $referencePrice,
            $listPrice,
            $regulationPrice,
            $mockCurrency
        );

        $this->assertSame($unitPrice, $calculatedPrice->getUnitPrice());
        $this->assertSame($totalPrice, $calculatedPrice->getTotalPrice());
        $this->assertSame($calculatedTaxes, $calculatedPrice->getCalculatedTaxes());
        $this->assertSame($taxRules, $calculatedPrice->getTaxRules());
        $this->assertSame($quantity, $calculatedPrice->getQuantity());
        $this->assertSame($referencePrice, $calculatedPrice->getReferencePrice());
        $this->assertSame($listPrice, $calculatedPrice->getListPrice());
        $this->assertSame($regulationPrice, $calculatedPrice->getRegulationPrice());
        $this->assertSame($mockCurrency, $calculatedPrice->getCurrency());
    }
}