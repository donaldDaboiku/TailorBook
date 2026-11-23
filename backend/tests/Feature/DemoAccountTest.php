<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Measurement;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_read_only_and_cannot_send_customer_whatsapp_messages(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_demo_account' => true])->save();
        $business = Business::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create(['business_id' => $business->id]);
        $payment = Payment::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.is_demo_account', true);

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonPath('data.0.whatsapp_url', null);

        $this->getJson("/api/customers/{$customer->id}/whatsapp-templates")
            ->assertForbidden();

        $this->getJson("/api/customers/{$customer->id}/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.whatsapp_url', null);

        $this->postJson('/api/customers', [
            'name' => 'Blocked customer',
            'phone' => '08031234567',
            'gender' => 'female',
        ])->assertForbidden();

        $this->putJson('/api/business', [
            'name' => 'Changed shop',
            'phone' => '08031234567',
            'country' => 'NG',
            'state' => 'Lagos',
            'measurement_unit' => 'cm',
            'currency' => 'NGN',
        ])->assertForbidden();

        $this->postJson('/api/billing/checkout')->assertForbidden();
        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseMissing('customers', ['name' => 'Blocked customer']);
        $this->assertSame($business->name, $business->fresh()->name);
    }

    public function test_demo_accounts_cannot_access_administrative_routes(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['is_demo_account' => true])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/shops')->assertForbidden();
    }

    public function test_demo_account_data_is_separate_and_refreshed_by_demo_reset(): void
    {
        Mail::fake();
        config([
            'services.demo.email' => 'demo@example.test',
            'services.demo.password' => 'a-strong-demo-password',
        ]);

        $realUser = User::factory()->create();
        $realBusiness = Business::factory()->create(['user_id' => $realUser->id]);
        $realCustomer = Customer::factory()->create(['business_id' => $realBusiness->id]);

        $this->artisan('demo:reset')->assertSuccessful();

        $demoUser = User::query()->where('is_demo_account', true)->firstOrFail();
        $demoBusiness = $demoUser->business()->firstOrFail();

        $this->assertSame('TailorMate Demo Shop', $demoUser->name);
        $this->assertSame('Demo Fashion House', $demoBusiness->name);
        $this->assertSame(7, Customer::query()->where('business_id', $demoBusiness->id)->count());
        $this->assertSame(2, Measurement::query()->where('business_id', $demoBusiness->id)->count());
        $this->assertSame(3, $demoBusiness->customerJobs()->count());
        $this->assertSame(
            ['completed', 'in_progress', 'pending'],
            $demoBusiness->customerJobs()->orderBy('status')->pluck('status')->all(),
        );
        $this->assertSame(3, $demoBusiness->payments()->count());
        $this->assertSame(
            '950.00',
            (string) $demoBusiness->payments()
                ->whereHas('job', fn ($query) => $query->where('title', 'Ceremony dress'))
                ->value('amount'),
        );
        $this->assertSame(3, $demoBusiness->expenses()->count());
        $this->assertNotSame($realBusiness->id, $demoBusiness->id);

        Customer::query()->where('business_id', $demoBusiness->id)->firstOrFail()->delete();

        $this->getJson('/api/health')->assertOk();
        $this->assertSame(6, Customer::query()->where('business_id', $demoBusiness->id)->count());

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(7, Customer::query()->where('business_id', $demoBusiness->id)->count());
        $this->assertSame('Demo Fashion House', $demoBusiness->fresh()->name);
        $this->assertSame($realCustomer->id, $realCustomer->fresh()->id);
        $this->assertDatabaseHas('users', [
            'id' => $realUser->id,
            'is_demo_account' => false,
        ]);

        $this->getJson('/api/public/presence')
            ->assertOk()
            ->assertJsonPath('data.subscribers', 1);
    }
}
