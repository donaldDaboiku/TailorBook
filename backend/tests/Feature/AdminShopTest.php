<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Services\PaystackBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_registered_shops(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@tailormate.test',
        ]);

        $tailor = User::factory()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'last_login_at' => '2026-10-07 09:00:00',
        ]);

        Business::factory()->create([
            'user_id' => $tailor->id,
            'name' => 'Ada Atelier',
            'phone' => '08031234567',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/shops')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.owner_name', 'Ada')
            ->assertJsonPath('data.0.email', 'ada@example.com')
            ->assertJsonPath('data.0.shop_name', 'Ada Atelier')
            ->assertJsonPath('data.0.phone', '08031234567')
            ->assertJsonPath('data.0.last_login_at', '2026-10-07T09:00:00.000000Z');
    }

    public function test_tailors_cannot_access_admin_shops(): void
    {
        $tailor = User::factory()->create(['role' => UserRole::Tailor]);
        Business::factory()->create(['user_id' => $tailor->id]);

        Sanctum::actingAs($tailor);

        $this->getJson('/api/admin/shops')
            ->assertForbidden();
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password',
            'last_login_at' => null,
        ]);
        Business::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'tailor');

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_admin_can_suspend_and_activate_a_shop(): void
    {
        $admin = User::factory()->admin()->create();
        $tailor = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password',
        ]);
        Business::factory()->create([
            'user_id' => $tailor->id,
            'name' => 'Ada Atelier',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/shops/{$tailor->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.suspended', true)
            ->assertJsonPath('message', 'Shop suspended.');

        $this->assertNotNull($tailor->fresh()->suspended_at);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'This account is suspended. Contact support.');

        $this->postJson("/api/admin/shops/{$tailor->id}/unsuspend")
            ->assertOk()
            ->assertJsonPath('data.suspended', false);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_admin_can_set_subscription_and_expired_shops_are_blocked_from_shop_apis(): void
    {
        $admin = User::factory()->admin()->create();
        $tailor = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password',
        ]);
        Business::factory()->create([
            'user_id' => $tailor->id,
            'name' => 'Ada Atelier',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/shops/{$tailor->id}/subscription", [
            'subscription_status' => 'subscribed',
            'subscribed_until' => '2026-12-31',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription_status', 'subscribed')
            ->assertJsonPath('data.subscription_access', 'subscribed')
            ->assertJsonPath('data.subscribed_until', '2026-12-31');

        $this->putJson("/api/admin/shops/{$tailor->id}/subscription", [
            'subscription_status' => 'subscribed',
            'subscribed_until' => '2020-01-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription_access', 'expired');

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.subscription_access', 'expired');

        Sanctum::actingAs($tailor->fresh());
        $this->getJson('/api/customers')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/shops/{$tailor->id}/subscription", [
            'subscription_status' => 'free',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription_access', 'free')
            ->assertJsonPath('data.subscribed_until', null);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_admin_can_set_a_shop_state(): void
    {
        $admin = User::factory()->admin()->create();
        $tailor = User::factory()->create();
        $shop = Business::factory()->create([
            'user_id' => $tailor->id,
            'state' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/shops/{$tailor->id}/state", [
            'state' => 'Lagos',
        ])
            ->assertOk()
            ->assertJsonPath('data.state', 'Lagos');

        $this->assertSame('Lagos', $shop->fresh()->state);

        $this->putJson("/api/admin/shops/{$tailor->id}/state", [
            'state' => 'Paris',
        ])->assertUnprocessable();

        Sanctum::actingAs($tailor);

        $this->putJson("/api/admin/shops/{$tailor->id}/state", [
            'state' => 'Oyo',
        ])->assertForbidden();
    }

    public function test_admin_can_raise_style_allowance_and_the_pack_price(): void
    {
        $admin = User::factory()->admin()->create();
        $tailor = User::factory()->create();
        $shop = Business::factory()->create(['user_id' => $tailor->id]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/shops/{$tailor->id}/catalogue", [
            'extra' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.catalogue_extra', 10)
            ->assertJsonPath('data.catalogue_limit', 50)
            ->assertJsonPath('data.catalogue_used', 0);

        $this->assertSame(10, $shop->fresh()->catalogue_extra);

        $this->putJson("/api/admin/shops/{$tailor->id}/catalogue", [
            'free' => 25,
            'extra' => 15,
            'amount' => 1800,
        ])
            ->assertOk()
            ->assertJsonPath('data.catalogue_free', 25)
            ->assertJsonPath('data.catalogue_extra', 15)
            ->assertJsonPath('data.catalogue_limit', 40)
            ->assertJsonPath('data.catalogue_pack_amount', 1800);

        $fresh = $shop->fresh();
        $this->assertSame(180000, app(PaystackBilling::class)->cataloguePack($fresh)['amount']);
        $this->assertSame(40, app(PaystackBilling::class)->catalogueLimit($fresh));

        $this->putJson("/api/admin/shops/{$tailor->id}/catalogue", [
            'extra' => 15,
            'amount' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.catalogue_pack_amount', null);

        $this->putJson('/api/admin/catalogue-price', [
            'amount' => 2500,
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', 2500)
            ->assertJsonPath('data.amount_label', '₦2,500.00')
            ->assertJsonPath('data.pack', 10);

        $this->assertSame(250000, app(PaystackBilling::class)->cataloguePack()['amount']);

        Sanctum::actingAs($tailor);

        $this->putJson('/api/admin/catalogue-price', [
            'amount' => 500,
        ])->assertForbidden();

        $this->putJson("/api/admin/shops/{$tailor->id}/catalogue", [
            'extra' => 20,
        ])->assertForbidden();
    }
}
