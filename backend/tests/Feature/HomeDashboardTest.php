<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
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

        Sanctum::actingAs($user);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.outstanding_label', '₦30,000.00')
            ->assertJsonPath('data.active_jobs', 2)
            ->assertJsonPath('data.due_soon', 1)
            ->assertJsonPath('data.upcoming_jobs.0.title', 'Gown')
            ->assertJsonPath('data.upcoming_jobs.0.customer_name', 'Bola');
    }
}
