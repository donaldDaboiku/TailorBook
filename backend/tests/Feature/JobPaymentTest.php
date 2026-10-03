<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tailor_can_record_a_job_and_payments_and_see_outstanding(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create(['business_id' => $user->business->id]);

        Sanctum::actingAs($user);

        $jobId = $this->postJson("/api/customers/{$customer->id}/jobs", [
            'title' => 'Gown',
            'agreed_amount' => '150000',
            'service_date' => '2026-10-03',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Gown')
            ->assertJsonPath('data.agreed_amount', '150000.00')
            ->assertJsonPath('data.outstanding', '150000.00')
            ->json('data.id');

        $this->postJson("/api/customers/{$customer->id}/payments", [
            'amount' => '50000',
            'paid_on' => '2026-10-03',
            'method' => 'bank_transfer',
            'customer_job_id' => $jobId,
            'reference' => 'TRX-1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '50000.00')
            ->assertJsonPath('data.method', 'bank_transfer')
            ->assertJsonPath('data.job_title', 'Gown');

        $this->postJson("/api/customers/{$customer->id}/payments", [
            'amount' => '30000',
            'paid_on' => '2026-10-04',
            'method' => 'cash',
        ])->assertCreated();

        $this->getJson("/api/customers/{$customer->id}/jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.outstanding', '100000.00')
            ->assertJsonPath('data.paid_amount', '50000.00');

        $this->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.finance.total_agreed', '150000.00')
            ->assertJsonPath('data.finance.total_paid', '80000.00')
            ->assertJsonPath('data.finance.outstanding', '70000.00')
            ->assertJsonPath('data.finance.outstanding_label', '₦70,000.00');
    }

    public function test_archived_payments_and_jobs_drop_out_of_the_balance(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create(['business_id' => $user->business->id]);

        Sanctum::actingAs($user);

        $jobId = $this->postJson("/api/customers/{$customer->id}/jobs", [
            'title' => 'Suit',
            'agreed_amount' => '100000',
            'service_date' => '2026-10-03',
        ])->json('data.id');

        $paymentId = $this->postJson("/api/customers/{$customer->id}/payments", [
            'amount' => '40000',
            'paid_on' => '2026-10-03',
            'method' => 'pos',
            'customer_job_id' => $jobId,
        ])->json('data.id');

        $this->deleteJson("/api/customers/{$customer->id}/payments/{$paymentId}")
            ->assertOk();

        $this->getJson("/api/customers/{$customer->id}")
            ->assertJsonPath('data.finance.outstanding', '100000.00');

        $this->deleteJson("/api/customers/{$customer->id}/jobs/{$jobId}")
            ->assertOk();

        $this->assertSoftDeleted(CustomerJob::withTrashed()->findOrFail($jobId));

        $this->getJson("/api/customers/{$customer->id}")
            ->assertJsonPath('data.finance.total_agreed', '0.00')
            ->assertJsonPath('data.finance.outstanding', '0.00');
    }

    public function test_zero_payments_are_rejected_and_other_shops_are_blocked(): void
    {
        $owner = $this->tailor();
        $other = $this->tailor('other@example.com');
        $customer = Customer::factory()->create(['business_id' => $owner->business->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/customers/{$customer->id}/payments", [
            'amount' => '0',
            'paid_on' => '2026-10-03',
            'method' => 'cash',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'The payment amount must be greater than zero.');

        Sanctum::actingAs($other);

        $this->getJson("/api/customers/{$customer->id}/jobs")->assertForbidden();
        $this->postJson("/api/customers/{$customer->id}/payments", [
            'amount' => '1000',
            'paid_on' => '2026-10-03',
            'method' => 'cash',
        ])->assertForbidden();
    }

    public function test_payment_cannot_point_at_another_customers_job(): void
    {
        $user = $this->tailor();
        $grace = Customer::factory()->create(['business_id' => $user->business->id]);
        $chinedu = Customer::factory()->create(['business_id' => $user->business->id]);
        $job = CustomerJob::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $chinedu->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/customers/{$grace->id}/payments", [
            'amount' => '10000',
            'paid_on' => '2026-10-03',
            'method' => 'cash',
            'customer_job_id' => $job->id,
        ])->assertUnprocessable();
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
            'country' => 'NG',
            'currency' => 'NGN',
        ]);

        return $user->fresh(['business']);
    }
}
