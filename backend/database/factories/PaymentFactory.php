<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'customer_job_id' => null,
            'amount' => fake()->randomElement(['10000.00', '20000.00', '50000.00']),
            'paid_on' => now()->toDateString(),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'reference' => null,
            'note' => null,
        ];
    }
}
