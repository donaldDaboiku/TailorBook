<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Measurement;
use App\Models\MeasurementField;
use App\Models\MeasurementValue;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_a_shop_downloads_only_its_own_records(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $shop = Business::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Ada Stitches',
            'measurement_unit' => 'cm',
        ]);
        $customer = Customer::factory()->create([
            'business_id' => $shop->id,
            'name' => 'Bola',
            'phone' => '08031111111',
        ]);
        CustomerJob::factory()->create([
            'business_id' => $shop->id,
            'customer_id' => $customer->id,
            'title' => 'Agbada',
        ]);
        $measurement = Measurement::factory()->create([
            'business_id' => $shop->id,
            'customer_id' => $customer->id,
        ]);
        $field = MeasurementField::query()->where('key', 'bust')->firstOrFail();
        MeasurementValue::query()->create([
            'measurement_id' => $measurement->id,
            'measurement_field_id' => $field->id,
            'value_cm' => '90.00',
        ]);
        $archived = Customer::factory()->create([
            'business_id' => $shop->id,
            'name' => 'Hidden Client',
        ]);
        $archived->delete();

        $other = User::factory()->create();
        $otherShop = Business::factory()->create(['user_id' => $other->id]);
        Customer::factory()->create([
            'business_id' => $otherShop->id,
            'name' => 'Secret Client',
        ]);

        Sanctum::actingAs($owner);

        $this->get('/api/business/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('Ada Stitches')
            ->assertSee('owner@example.com')
            ->assertSee('Bola')
            ->assertSee('Agbada')
            ->assertSee('Bust')
            ->assertSee('90.0')
            ->assertSee('Hidden Client')
            ->assertDontSee('Secret Client');
    }

    public function test_an_expired_shop_can_still_download_its_records(): void
    {
        $owner = User::factory()->create([
            'subscription_status' => SubscriptionStatus::Subscribed,
            'subscribed_until' => now()->subDay()->toDateString(),
        ]);
        $shop = Business::factory()->create(['user_id' => $owner->id]);
        Customer::factory()->create([
            'business_id' => $shop->id,
            'name' => 'Bola',
        ]);

        Sanctum::actingAs($owner);

        $this->get('/api/business/export')
            ->assertOk()
            ->assertSee('Bola');
    }

    public function test_a_guest_cannot_download_records(): void
    {
        $this->getJson('/api/business/export')->assertUnauthorized();
    }
}
