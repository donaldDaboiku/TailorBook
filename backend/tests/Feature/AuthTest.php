<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tailor_can_register_with_a_shop_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registrationPayload());

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.name', 'Ada')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.business.name', 'Ada Atelier')
            ->assertJsonPath('user.business.phone', '08031234567')
            ->assertJsonPath('user.business.whatsapp_phone', '08031234567')
            ->assertJsonPath('user.business.country', 'NG')
            ->assertJsonPath('user.business.measurement_unit', 'in')
            ->assertJsonPath('user.business.currency', 'NGN')
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseHas('businesses', [
            'name' => 'Ada Atelier',
            'user_id' => User::query()->where('email', 'ada@example.com')->value('id'),
        ]);
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload())->assertCreated();

        $this->postJson('/api/auth/register', $this->registrationPayload([
            'phone' => '08037654321',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'That email is already registered.');
    }

    public function test_a_tailor_can_login_and_read_their_profile(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload())->assertCreated();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.business.name', 'Ada Atelier')
            ->assertJsonStructure(['token']);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada')
            ->assertJsonPath('data.business.name', 'Ada Atelier');
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload())->assertCreated();

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Those login details are wrong.');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $register = $this->postJson('/api/auth/register', $this->registrationPayload())->assertCreated();
        $token = $register->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Signed out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // PHPUnit reuses the app between requests; clear the cached sanctum user.
        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_a_user_with_sanctum_acting_as_can_read_their_profile(): void
    {
        $user = User::factory()->create();
        $user->business()->create([
            'name' => 'Ada Atelier',
            'phone' => '08030000000',
            'whatsapp_phone' => '08030000000',
            'country' => 'NG',
            'measurement_unit' => 'in',
            'currency' => 'NGN',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Ada Atelier');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'business_name' => 'Ada Atelier',
            'phone' => '08031234567',
            'country' => 'NG',
            'measurement_unit' => 'in',
            'currency' => 'NGN',
        ], $overrides);
    }
}
