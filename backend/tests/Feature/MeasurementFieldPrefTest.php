<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeasurementFieldPrefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_a_shop_can_rename_and_hide_measurement_fields(): void
    {
        $user = $this->tailor();

        Sanctum::actingAs($user);

        $this->putJson('/api/measurement-templates/female/fields', [
            'fields' => [
                ['key' => 'bust', 'label' => 'Chest / Bust', 'enabled' => true],
                ['key' => 'waist', 'label' => 'Waist', 'enabled' => true],
                ['key' => 'hip', 'label' => 'Hip', 'enabled' => false],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'female');

        $all = $this->getJson('/api/measurement-templates')
            ->assertOk()
            ->json('data');

        $female = collect($all)->firstWhere('slug', 'female');
        $bust = collect($female['fields'])->firstWhere('key', 'bust');
        $hip = collect($female['fields'])->firstWhere('key', 'hip');

        $this->assertSame('Chest / Bust', $bust['label']);
        $this->assertTrue($bust['enabled']);
        $this->assertFalse($hip['enabled']);

        $enabledOnly = $this->getJson('/api/measurement-templates?enabled_only=1')
            ->assertOk()
            ->json('data');

        $femaleEnabled = collect($enabledOnly)->firstWhere('slug', 'female');
        $keys = collect($femaleEnabled['fields'])->pluck('key');

        $this->assertTrue($keys->contains('bust'));
        $this->assertFalse($keys->contains('hip'));
    }

    private function tailor(): User
    {
        $user = User::factory()->create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
            'name' => 'Ada Atelier',
            'phone' => '08030000000',
        ]);

        return $user->fresh(['business']);
    }
}
