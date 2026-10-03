<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Measurement;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeasurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_templates_are_available(): void
    {
        Sanctum::actingAs($this->tailor());

        $this->getJson('/api/measurement-templates')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'female')
            ->assertJsonPath('data.0.fields.0.key', 'bust');
    }

    public function test_saving_measurements_creates_a_new_version_in_centimeters(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create(['business_id' => $user->business->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/customers/{$customer->id}/measurements", [
            'template_slug' => 'female',
            'taken_on' => '2026-03-14',
            'unit' => 'in',
            'values' => [
                'bust' => 37,
                'waist' => 31,
                'hip' => 41,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.unit', 'in')
            ->assertJsonPath('data.values.0.key', 'bust')
            ->assertJsonPath('data.values.0.value', '37.00')
            ->assertJsonPath('data.values.0.value_cm', '93.98');

        $this->postJson("/api/customers/{$customer->id}/measurements", [
            'template_slug' => 'female',
            'taken_on' => '2026-10-03',
            'unit' => 'in',
            'values' => [
                'bust' => 38,
                'waist' => 32,
                'hip' => 42,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.values.0.value', '38.00')
            ->assertJsonPath('data.values.0.previous_value', '37.00')
            ->assertJsonPath('data.values.0.change', '+1.00');

        $this->assertSame(2, $customer->measurements()->count());
    }

    public function test_history_is_newest_first_and_old_values_cannot_be_changed(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create(['business_id' => $user->business->id]);

        Sanctum::actingAs($user);

        $older = $this->postJson("/api/customers/{$customer->id}/measurements", [
            'template_slug' => 'female',
            'taken_on' => '2026-03-14',
            'unit' => 'in',
            'values' => ['bust' => 37],
        ])->json('data.id');

        $newer = $this->postJson("/api/customers/{$customer->id}/measurements", [
            'template_slug' => 'female',
            'taken_on' => '2026-10-03',
            'unit' => 'in',
            'values' => ['bust' => 38],
        ])->json('data.id');

        $this->getJson("/api/customers/{$customer->id}/measurements")
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer)
            ->assertJsonPath('data.1.id', $older);

        $measurement = Measurement::query()->findOrFail($older);
        $value = $measurement->values()->firstOrFail();
        $value->update(['value_cm' => '10.00']);

        $this->assertSame('93.98', $value->fresh()->value_cm);
    }

    public function test_another_shop_cannot_read_measurements(): void
    {
        $owner = $this->tailor();
        $other = $this->tailor('other@example.com');
        $customer = Customer::factory()->create(['business_id' => $owner->business->id]);

        Sanctum::actingAs($owner);
        $id = $this->postJson("/api/customers/{$customer->id}/measurements", [
            'template_slug' => 'male',
            'taken_on' => '2026-10-03',
            'unit' => 'cm',
            'values' => ['chest' => 96],
        ])->json('data.id');

        Sanctum::actingAs($other);

        $this->getJson("/api/customers/{$customer->id}/measurements")->assertForbidden();
        $this->getJson("/api/customers/{$customer->id}/measurements/{$id}")->assertForbidden();
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
            'country' => 'NG',
            'measurement_unit' => 'in',
        ]);

        return $user->fresh(['business']);
    }
}
