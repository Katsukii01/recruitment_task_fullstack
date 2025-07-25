<?php

declare(strict_types=1);

namespace Integration;

use PHPUnit\Framework\TestCase;
use App\CurrencyCalculator;

class CurrencyCalculatorTest extends TestCase
{
    public function testCalculateBuyRateForEurAndUsd(): void
    {
        $this->assertSame(4.85, CurrencyCalculator::calculateBuyRate('EUR', 5.0)); // 5.0 - 0.15
        $this->assertSame(3.85, CurrencyCalculator::calculateBuyRate('USD', 4.0)); // 4.0 - 0.15
    }

    public function testCalculateBuyRateForOtherCurrencies(): void
    {
        $this->assertNull(CurrencyCalculator::calculateBuyRate('CZK', 1.0));
        $this->assertNull(CurrencyCalculator::calculateBuyRate('IDR', 1.0));
        $this->assertNull(CurrencyCalculator::calculateBuyRate('BRL', 1.0));
    }

    public function testCalculateSellRateForEurAndUsd(): void
    {
        $this->assertSame(5.11, CurrencyCalculator::calculateSellRate('EUR', 5.0)); // 5.0 + 0.11
        $this->assertSame(4.11, CurrencyCalculator::calculateSellRate('USD', 4.0)); // 4.0 + 0.11
    }

    public function testCalculateSellRateForOtherCurrencies(): void
    {
        $this->assertSame(1.2, CurrencyCalculator::calculateSellRate('CZK', 1.0)); // 1.0 + 0.2
        $this->assertSame(1.2, CurrencyCalculator::calculateSellRate('IDR', 1.0)); // 1.0 + 0.2
        $this->assertSame(1.2, CurrencyCalculator::calculateSellRate('BRL', 1.0)); // 1.0 + 0.2
    }

    public function testCalculateSellRateForUnsupportedCurrency(): void
    {
        $this->assertNull(CurrencyCalculator::calculateSellRate('GBP', 5.0));
    }
} 