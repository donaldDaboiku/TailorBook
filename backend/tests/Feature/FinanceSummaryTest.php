<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_summary_shows_today_week_month_and_outstanding(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create(['business_id' => $user->business->id]);
        $category = ExpenseCategory::query()->where('slug', 'fabric')->firstOrFail();

        CustomerJob::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $customer->id,
            'agreed_amount' => '150000.00',
            'service_date' => '2026-10-01',
        ]);

        Payment::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $customer->id,
            'amount' => '50000.00',
            'paid_on' => '2026-10-07',
        ]);

        Payment::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $customer->id,
            'amount' => '20000.00',
            'paid_on' => '2026-10-06',
        ]);

        Payment::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $customer->id,
            'amount' => '10000.00',
            'paid_on' => '2026-09-30',
        ]);

        Expense::factory()->create([
            'business_id' => $user->business->id,
            'expense_category_id' => $category->id,
            'amount' => '5000.00',
            'spent_on' => '2026-10-07',
        ]);

        Expense::factory()->create([
            'business_id' => $user->business->id,
            'expense_category_id' => $category->id,
            'amount' => '3000.00',
            'spent_on' => '2026-10-05',
        ]);

        Expense::factory()->create([
            'business_id' => $user->business->id,
            'expense_category_id' => $category->id,
            'amount' => '8000.00',
            'spent_on' => '2026-09-15',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.outstanding', '70000.00')
            ->assertJsonPath('data.outstanding_label', '₦70,000.00')
            ->assertJsonPath('data.periods.today.from', '2026-10-07')
            ->assertJsonPath('data.periods.today.to', '2026-10-07')
            ->assertJsonPath('data.periods.today.income', '50000.00')
            ->assertJsonPath('data.periods.today.expenses', '5000.00')
            ->assertJsonPath('data.periods.today.net', '45000.00')
            ->assertJsonPath('data.periods.week.from', '2026-10-05')
            ->assertJsonPath('data.periods.week.to', '2026-10-11')
            ->assertJsonPath('data.periods.week.income', '70000.00')
            ->assertJsonPath('data.periods.week.expenses', '8000.00')
            ->assertJsonPath('data.periods.week.net', '62000.00')
            ->assertJsonPath('data.periods.month.from', '2026-10-01')
            ->assertJsonPath('data.periods.month.to', '2026-10-31')
            ->assertJsonPath('data.periods.month.income', '70000.00')
            ->assertJsonPath('data.periods.month.expenses', '8000.00')
            ->assertJsonPath('data.periods.month.net', '62000.00');
    }

    public function test_archived_rows_and_other_shops_are_excluded(): void
    {
        $owner = $this->tailor();
        $other = $this->tailor('other@example.com');
        $ownerCustomer = Customer::factory()->create(['business_id' => $owner->business->id]);
        $otherCustomer = Customer::factory()->create(['business_id' => $other->business->id]);
        $category = ExpenseCategory::query()->where('slug', 'rent')->firstOrFail();

        CustomerJob::factory()->create([
            'business_id' => $owner->business->id,
            'customer_id' => $ownerCustomer->id,
            'agreed_amount' => '100000.00',
        ]);

        $kept = Payment::factory()->create([
            'business_id' => $owner->business->id,
            'customer_id' => $ownerCustomer->id,
            'amount' => '25000.00',
            'paid_on' => '2026-10-07',
        ]);

        $archived = Payment::factory()->create([
            'business_id' => $owner->business->id,
            'customer_id' => $ownerCustomer->id,
            'amount' => '15000.00',
            'paid_on' => '2026-10-07',
        ]);
        $archived->delete();

        Expense::factory()->create([
            'business_id' => $owner->business->id,
            'expense_category_id' => $category->id,
            'amount' => '4000.00',
            'spent_on' => '2026-10-07',
        ]);

        $gone = Expense::factory()->create([
            'business_id' => $owner->business->id,
            'expense_category_id' => $category->id,
            'amount' => '9000.00',
            'spent_on' => '2026-10-07',
        ]);
        $gone->delete();

        Payment::factory()->create([
            'business_id' => $other->business->id,
            'customer_id' => $otherCustomer->id,
            'amount' => '99999.00',
            'paid_on' => '2026-10-07',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.outstanding', '75000.00')
            ->assertJsonPath('data.periods.today.income', '25000.00')
            ->assertJsonPath('data.periods.today.expenses', '4000.00')
            ->assertJsonPath('data.periods.today.net', '21000.00');

        $this->assertNotNull($kept->fresh());
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
