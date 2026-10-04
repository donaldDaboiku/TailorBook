<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tailor_can_update_account_and_shop_settings(): void
    {
        $user = $this->tailor();

        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => 'Ada Okoro',
            'email' => 'ada.okoro@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Okoro')
            ->assertJsonPath('data.email', 'ada.okoro@example.com')
            ->assertJsonPath('data.business.name', 'Ada Atelier');

        $this->putJson('/api/business', [
            'name' => 'Ada Fashion House',
            'phone' => '08035551234',
            'whatsapp_phone' => '08035559999',
            'country' => 'NG',
            'measurement_unit' => 'cm',
            'currency' => 'NGN',
        ])
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Ada Fashion House')
            ->assertJsonPath('data.business.phone', '08035551234')
            ->assertJsonPath('data.business.whatsapp_phone', '08035559999')
            ->assertJsonPath('data.business.measurement_unit', 'cm');

        $this->assertDatabaseHas('businesses', [
            'user_id' => $user->id,
            'name' => 'Ada Fashion House',
            'measurement_unit' => 'cm',
        ]);
    }

    public function test_profile_email_must_stay_unique(): void
    {
        $owner = $this->tailor();
        $this->tailor('taken@example.com');

        Sanctum::actingAs($owner);

        $this->putJson('/api/auth/profile', [
            'name' => 'Ada',
            'email' => 'taken@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'That email is already registered.');
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
            'currency' => 'NGN',
            'measurement_unit' => 'in',
        ]);

        return $user->fresh(['business']);
    }
}
