<?php

namespace App\Enums;

/**
 * Fixed test exchange rates for the five supported currencies.
 *
 *   1 USD = 1,600  NGN
 *   1 USD = 0.79   GBP
 *   1 USD = 15.50  GHS
 *   1 USD = 129.00 KES
 *
 */
class ExchangeRate
{
    /**
     * Flat rate table.
     * $rates[FROM][TO] = multiplier
     */
    private static array $rates = [
        'USD' => [
            'USD' => 1.0,
            'NGN' => 1600.0,
            'GBP' => 0.79,
            'GHS' => 15.50,
            'KES' => 129.0,
        ],
        'NGN' => [
            'NGN' => 1.0,
            'USD' => 0.000625,    // 1 / 1600
            'GBP' => 0.000494,    // 0.79 / 1600
            'GHS' => 0.009688,    // 15.50 / 1600
            'KES' => 0.080625,    // 129.00 / 1600
        ],
        'GBP' => [
            'GBP' => 1.0,
            'USD' => 1.2658,      // 1 / 0.79
            'NGN' => 2025.32,     // 1600 / 0.79
            'GHS' => 19.62,       // 15.50 / 0.79
            'KES' => 163.29,      // 129.00 / 0.79
        ],
        'GHS' => [
            'GHS' => 1.0,
            'USD' => 0.06452,     // 1 / 15.50
            'NGN' => 103.23,      // 1600 / 15.50
            'GBP' => 0.05097,     // 0.79 / 15.50
            'KES' => 8.3226,      // 129.00 / 15.50
        ],
        'KES' => [
            'KES' => 1.0,
            'USD' => 0.007752,    // 1 / 129
            'NGN' => 12.403,      // 1600 / 129
            'GBP' => 0.006124,    // 0.79 / 129
            'GHS' => 0.12016,     // 15.50 / 129
        ],
    ];

    /**
     * Get the exchange rate from one currency to another.
     * Returns 1.0 as a safe fallback for unknown pairs.
     */
    public static function getRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        return static::$rates[$from][$to] ?? 1.0;
    }

    /**
     * Convert an amount from one currency to another.
     */
    public static function convert(float $amount, string $from, string $to): float
    {
        return round($amount * static::getRate($from, $to), 2);
    }

    /**
     * Returns all supported currency codes.
     *
     * @return string[]
     */
    public static function supportedCurrencies(): array
    {
        return array_keys(static::$rates);
    }
}
