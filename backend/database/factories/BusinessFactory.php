<?php

namespace Database\Factories;

use App\Enums\MeasurementUnit;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '0803'.fake()->unique()->numerify('#######');

        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'phone' => $phone,
            'whatsapp_phone' => $phone,
            'country' => 'NG',
            'measurement_unit' => MeasurementUnit::Inches,
            'currency' => 'NGN',
        ];
    }
}
