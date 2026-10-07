<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_can_request_and_complete_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'old-password',
        ]);
        Business::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'ada@example.com',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If that email is registered, a reset link was sent.',
            );

        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'ada@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated. You can sign in now.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_admin_can_reset_a_shop_password(): void
    {
        $admin = User::factory()->admin()->create();
        $tailor = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'old-password',
            'role' => UserRole::Tailor,
        ]);
        Business::factory()->create(['user_id' => $tailor->id]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/shops/{$tailor->id}/password", [
            'password' => 'temp-password',
            'password_confirmation' => 'temp-password',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com');

        $this->assertTrue(Hash::check('temp-password', $tailor->fresh()->password));

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'temp-password',
        ])->assertOk();
    }
}
