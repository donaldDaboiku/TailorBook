<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Balance
{
    /**
     * @param  array<int, mixed>  $agreedAmounts
     * @param  array<int, mixed>  $paymentAmounts
     */
    public static function outstanding(array $agreedAmounts, array $paymentAmounts): string
    {
        $agreed = self::sum($agreedAmounts);
        $paid = self::sum($paymentAmounts);

        return (string) $agreed->minus($paid)->toScale(2, RoundingMode::Unnecessary);
    }

    /**
     * @param  array<int, mixed>  $amounts
     */
    private static function sum(array $amounts): BigDecimal
    {
        $total = BigDecimal::zero();

        foreach ($amounts as $amount) {
            $total = $total->plus(BigDecimal::of((string) $amount));
        }

        return $total;
    }
}
