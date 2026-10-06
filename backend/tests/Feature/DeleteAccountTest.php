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

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_requires_the_correct_password(): void
    {
        $user = $this->tailor();

        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/account', [
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'That password is wrong.');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_tailor_can_delete_their_account_with_password(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create([
            'business_id' => $user->business->id,
        ]);
        $job = CustomerJob::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $customer->id,
            'agreed_amount' => '50000.00',
        ]);
        Payment::factory()->create([
            'business_id' => $user->business->id,
            'customer_id' => $customer->id,
            'customer_job_id' => $job->id,
            'amount' => '10000.00',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/account', [
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Account deleted.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('businesses', ['id' => $user->business->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('customer_jobs', ['id' => $job->id]);
    }

    private function tailor(string $email = 'ada@example.com'): User
    {
        $user = User::factory()->create([
            'name' => 'Ada',
            'email' => $email,
            'password' => 'password',
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
