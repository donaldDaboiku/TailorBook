<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_one_tailor_cannot_read_or_change_another_shops_records(): void
    {
        $owner = User::factory()->create();
        $shop = Business::factory()->create(['user_id' => $owner->id]);
        $customer = Customer::factory()->create([
            'business_id' => $shop->id,
            'name' => 'Bola',
        ]);
        $job = CustomerJob::factory()->create([
            'business_id' => $shop->id,
            'customer_id' => $customer->id,
            'title' => 'Agbada',
        ]);
        $payment = Payment::factory()->create([
            'business_id' => $shop->id,
            'customer_id' => $customer->id,
            'customer_job_id' => $job->id,
            'method' => 'cash',
        ]);
        $templateId = MeasurementTemplate::query()->where('slug', 'female')->value('id');
        $measurement = Measurement::factory()->create([
            'business_id' => $shop->id,
            'customer_id' => $customer->id,
            'measurement_template_id' => $templateId,
        ]);

        $intruder = User::factory()->create();
        Business::factory()->create(['user_id' => $intruder->id, 'name' => 'Other Shop']);
        Sanctum::actingAs($intruder);

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Bola']);

        $this->getJson("/api/customers/{$customer->id}")->assertForbidden();
        $this->putJson("/api/customers/{$customer->id}", [
            'name' => 'Stolen',
            'phone' => '08030000000',
            'gender' => 'female',
        ])->assertForbidden();

        $this->getJson("/api/customers/{$customer->id}/measurements")->assertForbidden();
        $this->getJson("/api/customers/{$customer->id}/measurements/{$measurement->id}")->assertForbidden();
        $this->postJson("/api/customers/{$customer->id}/measurements", [
            'template_slug' => 'female',
            'unit' => 'cm',
            'taken_on' => now()->toDateString(),
            'values' => ['bust' => '90'],
        ])->assertForbidden();

        $this->getJson("/api/customers/{$customer->id}/jobs")->assertForbidden();
        $this->getJson("/api/customers/{$customer->id}/jobs/{$job->id}")->assertForbidden();
        $this->postJson("/api/customers/{$customer->id}/jobs", [
            'title' => 'Stolen job',
            'agreed_amount' => '1000',
            'service_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->getJson("/api/customers/{$customer->id}/payments")->assertForbidden();
        $this->postJson("/api/customers/{$customer->id}/payments", [
            'amount' => '1000',
            'paid_on' => now()->toDateString(),
            'method' => 'cash',
        ])->assertForbidden();
        $this->deleteJson("/api/customers/{$customer->id}/payments/{$payment->id}")->assertForbidden();

        $this->assertSame('Bola', $customer->fresh()->name);
        $this->assertSame(1, $customer->jobs()->count());
        $this->assertSame(1, $customer->payments()->count());
        $this->assertSame(1, $customer->measurements()->count());
    }
}
