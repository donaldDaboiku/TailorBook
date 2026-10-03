<?php

namespace Tests\Unit;

use App\Services\Units;
use PHPUnit\Framework\TestCase;

class UnitsTest extends TestCase
{
    public function test_inches_convert_to_centimeters(): void
    {
        $this->assertSame('96.52', Units::toCm('38', 'in'));
        $this->assertSame('38.00', Units::toCm('38', 'cm'));
    }

    public function test_centimeters_convert_back_to_inches_on_the_stepper(): void
    {
        $this->assertSame('38.00', Units::fromCm('96.52', 'in'));
        $this->assertSame('38.0', Units::fromCm('38', 'cm'));
    }

    public function test_display_rounding_matches_steppers(): void
    {
        $this->assertSame('38.25', Units::roundForUnit('38.2', 'in'));
        $this->assertSame('38.5', Units::roundForUnit('38.4', 'cm'));
    }

    public function test_change_uses_display_units(): void
    {
        $this->assertSame('+1.00', Units::change('96.52', '93.98', 'in'));
        $this->assertSame('-0.5', Units::change('37.5', '38', 'cm'));
    }
}
