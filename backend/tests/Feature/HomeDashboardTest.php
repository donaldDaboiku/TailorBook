<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_real_outstanding_and_jobs_due_this_week(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bola',
        ]);

        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'title' => 'Gown',
            'agreed_amount' => '20000.00',
            'service_date' => now()->addDays(2)->toDateString(),
        ]);

        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'title' => 'Agbada',
            'agreed_amount' => '10000.00',
            'service_date' => now()->subDays(10)->toDateString(),
        ]);

        Payment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'amount' => '5000.00',
            'paid_on' => now()->toDateString(),
            'method' => 'cash',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.outstanding_label', '₦25,000.00')
            ->assertJsonPath('data.received_week_label', '₦5,000.00')
            ->assertJsonPath('data.active_jobs', 2)
            ->assertJsonPath('data.due_soon', 1)
            ->assertJsonCount(0, 'data.due_today')
            ->assertJsonPath('data.upcoming_jobs.0.title', 'Gown')
            ->assertJsonPath('data.upcoming_jobs.0.customer_name', 'Bola');
    }

    public function test_home_lists_jobs_due_today(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bola',
        ]);

        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'title' => 'Agbada',
            'service_date' => now()->toDateString(),
        ]);

        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'title' => 'Gown',
            'service_date' => now()->addDay()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.due_today')
            ->assertJsonPath('data.due_today.0.title', 'Agbada')
            ->assertJsonPath('data.due_today.0.customer_name', 'Bola')
            ->assertJsonPath('data.due_today.0.customer_id', $customer->id);
    }

    public function test_job_lists_separate_completed_orders_from_upcoming_and_overdue_orders(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create(['business_id' => $business->id]);

        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
            'service_date' => now()->addDay()->toDateString(),
        ]);
        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'status' => 'in_progress',
            'service_date' => now()->subDay()->toDateString(),
        ]);
        CustomerJob::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'service_date' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data.upcoming')
            ->assertJsonPath('data.upcoming.0.status', 'pending')
            ->assertJsonCount(1, 'data.overdue')
            ->assertJsonPath('data.overdue.0.status', 'in_progress')
            ->assertJsonCount(1, 'data.completed')
            ->assertJsonPath('data.completed.0.status', 'completed');
    }
}
