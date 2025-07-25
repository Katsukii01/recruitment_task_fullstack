<?php

declare(strict_types=1);

namespace App;

class CurrencyCalculator
{
    private const BUY_MARGIN = [
        'EUR' => -0.15,
        'USD' => -0.15,
    ];
    private const SELL_MARGIN = [
        'EUR' => 0.11,
        'USD' => 0.11,
        'CZK' => 0.2,
        'IDR' => 0.2,
        'BRL' => 0.2,
    ];

    public static function calculateBuyRate(string $currency, float $mid): ?float
    {
        if (isset(self::BUY_MARGIN[$currency])) {
            return round($mid + self::BUY_MARGIN[$currency], 4);
        }
        return null;
    }

    public static function calculateSellRate(string $currency, float $mid): ?float
    {
        if (isset(self::SELL_MARGIN[$currency])) {
            return round($mid + self::SELL_MARGIN[$currency], 4);
        }
        return null;
    }
}
