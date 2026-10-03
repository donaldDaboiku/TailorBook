<?php

namespace Tests\Unit;

use App\Services\Balance;
use PHPUnit\Framework\TestCase;

class BalanceTest extends TestCase
{
    public function test_subtracts_payments_from_the_agreed_amount(): void
    {
        $this->assertSame(
            '70000.00',
            Balance::outstanding(['150000.00'], ['50000.00', '30000.00']),
        );
    }

    public function test_overpayment_stays_negative(): void
    {
        $this->assertSame('-20.00', Balance::outstanding(['100.00'], ['120.00']));
    }

    public function test_no_amounts_is_zero(): void
    {
        $this->assertSame('0.00', Balance::outstanding([], []));
    }
}
