<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '0803'.fake()->unique()->numerify('#######');

        return [
            'business_id' => Business::factory(),
            'name' => fake()->name(),
            'phone' => $phone,
            'whatsapp_phone' => $phone,
            'email' => fake()->optional()->safeEmail(),
            'gender' => fake()->optional()->randomElement(Gender::cases()),
            'address' => fake()->optional()->streetAddress(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
