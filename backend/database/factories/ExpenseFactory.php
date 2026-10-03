<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'expense_category_id' => ExpenseCategory::query()->where('slug', 'fabric')->value('id'),
            'amount' => fake()->randomElement(['2500.00', '5000.00', '12000.00']),
            'spent_on' => now()->toDateString(),
            'description' => fake()->optional()->words(3, true),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'note' => null,
        ];
    }
}
