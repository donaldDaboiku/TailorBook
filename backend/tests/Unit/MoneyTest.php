<?php

namespace Tests\Unit;

use App\Services\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_naira_with_two_decimals(): void
    {
        $this->assertSame('₦70,000.00', Money::format('70000'));
        $this->assertSame('₦1,500.50', Money::format('1500.5'));
    }

    public function test_normalizes_to_two_decimal_places(): void
    {
        $this->assertSame('150000.00', Money::normalize('150000'));
        $this->assertSame('80.10', Money::normalize('80.1'));
    }
}
