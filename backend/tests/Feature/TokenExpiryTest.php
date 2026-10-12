<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expired_token_is_rejected_and_a_fresh_token_still_works(): void
    {
        config(['sanctum.expiration' => 60]);

        $user = User::factory()->create();
        Business::factory()->create(['user_id' => $user->id]);

        $expired = $user->createToken('phone');
        $expired->accessToken->forceFill([
            'created_at' => now()->subMinutes(61),
        ])->save();

        $this->withToken($expired->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $fresh = $user->createToken('phone')->plainTextToken;

        $this->withToken($fresh)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}
