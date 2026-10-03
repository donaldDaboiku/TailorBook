<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Money
{
    public static function normalize(string|float|int $amount): string
    {
        return (string) BigDecimal::of((string) $amount)->toScale(2, RoundingMode::HalfUp);
    }

    public static function format(string|float|int $amount, string $currency = 'NGN'): string
    {
        $normalized = self::normalize($amount);
        $formatted = number_format((float) $normalized, 2, '.', ',');

        return match (strtoupper($currency)) {
            'NGN' => '₦'.$formatted,
            default => strtoupper($currency).' '.$formatted,
        };
    }
}
