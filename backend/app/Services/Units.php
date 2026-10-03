<?php

namespace App\Services;

use App\Enums\MeasurementUnit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Units
{
    private const INCH_TO_CM = '2.54';

    public static function toCm(string|float|int $value, MeasurementUnit|string $unit): string
    {
        $amount = BigDecimal::of((string) $value);
        $unit = self::unit($unit);

        if ($amount->isLessThanOrEqualTo(0)) {
            throw new \InvalidArgumentException('Measurement must be greater than zero.');
        }

        if ($unit === MeasurementUnit::Centimeters) {
            return (string) $amount->toScale(2, RoundingMode::HalfUp);
        }

        return (string) $amount
            ->multipliedBy(self::INCH_TO_CM)
            ->toScale(2, RoundingMode::HalfUp);
    }

    public static function fromCm(string|float|int $cm, MeasurementUnit|string $unit): string
    {
        $amount = BigDecimal::of((string) $cm);
        $unit = self::unit($unit);

        if ($unit === MeasurementUnit::Centimeters) {
            return self::roundForUnit($amount, $unit);
        }

        return self::roundForUnit(
            $amount->dividedBy(self::INCH_TO_CM, 8, RoundingMode::HalfUp),
            $unit,
        );
    }

    public static function roundForUnit(BigDecimal|string|float|int $value, MeasurementUnit|string $unit): string
    {
        $amount = $value instanceof BigDecimal ? $value : BigDecimal::of((string) $value);
        $unit = self::unit($unit);
        $step = $unit === MeasurementUnit::Inches ? '0.25' : '0.5';

        $steps = $amount
            ->dividedBy($step, 0, RoundingMode::HalfUp)
            ->multipliedBy($step);

        $scale = $unit === MeasurementUnit::Inches ? 2 : 1;

        return (string) $steps->toScale($scale, RoundingMode::HalfUp);
    }

    public static function change(string $currentCm, string $previousCm, MeasurementUnit|string $unit): string
    {
        $unit = self::unit($unit);
        $current = BigDecimal::of(self::fromCm($currentCm, $unit));
        $previous = BigDecimal::of(self::fromCm($previousCm, $unit));
        $diff = $current->minus($previous);
        $scale = $unit === MeasurementUnit::Inches ? 2 : 1;
        $formatted = (string) $diff->toScale($scale, RoundingMode::HalfUp);

        if ($diff->isPositive()) {
            return '+'.$formatted;
        }

        return $formatted;
    }

    private static function unit(MeasurementUnit|string $unit): MeasurementUnit
    {
        return $unit instanceof MeasurementUnit ? $unit : MeasurementUnit::from($unit);
    }
}
