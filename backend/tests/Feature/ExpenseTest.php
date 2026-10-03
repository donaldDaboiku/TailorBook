<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_categories_are_available(): void
    {
        Sanctum::actingAs($this->tailor());

        $this->getJson('/api/expense-categories')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.slug', 'fabric')
            ->assertJsonPath('data.9.slug', 'other');
    }

    public function test_a_tailor_can_record_and_list_expenses(): void
    {
        $user = $this->tailor();
        $category = ExpenseCategory::query()->where('slug', 'fabric')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/expenses', [
            'amount' => '4500',
            'spent_on' => '2026-10-03',
            'expense_category_id' => $category->id,
            'method' => 'cash',
            'description' => 'Ankara',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '4500.00')
            ->assertJsonPath('data.amount_label', '₦4,500.00')
            ->assertJsonPath('data.category.slug', 'fabric')
            ->assertJsonPath('data.description', 'Ankara');

        $this->getJson('/api/expenses?month=2026-10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category.name', 'Fabric');
    }

    public function test_zero_expenses_are_rejected_and_other_shops_are_isolated(): void
    {
        $owner = $this->tailor();
        $other = $this->tailor('other@example.com');
        $category = ExpenseCategory::query()->where('slug', 'transport')->firstOrFail();

        Sanctum::actingAs($owner);

        $this->postJson('/api/expenses', [
            'amount' => '0',
            'spent_on' => '2026-10-03',
            'expense_category_id' => $category->id,
            'method' => 'cash',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'The expense amount must be greater than zero.');

        $expenseId = $this->postJson('/api/expenses', [
            'amount' => '2000',
            'spent_on' => '2026-10-03',
            'expense_category_id' => $category->id,
            'method' => 'pos',
        ])->json('data.id');

        Sanctum::actingAs($other);

        $this->getJson('/api/expenses')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/expenses/{$expenseId}")->assertForbidden();
    }

    public function test_archiving_an_expense_hides_it_from_the_list(): void
    {
        $user = $this->tailor();
        $category = ExpenseCategory::query()->where('slug', 'rent')->firstOrFail();

        Sanctum::actingAs($user);

        $expenseId = $this->postJson('/api/expenses', [
            'amount' => '80000',
            'spent_on' => '2026-10-01',
            'expense_category_id' => $category->id,
            'method' => 'bank_transfer',
        ])->json('data.id');

        $this->deleteJson("/api/expenses/{$expenseId}")
            ->assertOk()
            ->assertJsonPath('message', 'Expense archived.');

        $this->assertSoftDeleted(Expense::withTrashed()->findOrFail($expenseId));
        $this->getJson('/api/expenses')->assertOk()->assertJsonCount(0, 'data');
    }

    private function tailor(string $email = 'ada@example.com'): User
    {
        $user = User::factory()->create([
            'name' => 'Ada',
            'email' => $email,
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Ada Atelier',
            'phone' => '08030000000',
            'currency' => 'NGN',
        ]);

        return $user->fresh(['business']);
    }
}
