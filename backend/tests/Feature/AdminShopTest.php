<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
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
}
