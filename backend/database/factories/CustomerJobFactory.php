<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerJob>
 */
class CustomerJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'title' => fake()->randomElement(['Gown', 'Suit', 'Shirt', 'Agbada']),
            'agreed_amount' => fake()->randomElement(['50000.00', '80000.00', '150000.00']),
            'service_date' => now()->toDateString(),
        ];
    }
}
