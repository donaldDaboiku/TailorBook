<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'measurement_template_id' => MeasurementTemplate::query()->where('slug', 'female')->value('id'),
            'taken_on' => now()->toDateString(),
        ];
    }
}
