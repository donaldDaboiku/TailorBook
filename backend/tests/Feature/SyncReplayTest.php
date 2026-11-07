<?php

namespace Tests\Feature;

use App\Mail\ShopNoticeMail;
use App\Models\Business;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\RecordRevision;
use App\Models\User;
use App\Services\SyncRecords;
use Illuminate\Database\UniqueConstraintViolationException;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PDOException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncReplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_the_same_client_id_does_not_create_a_second_customer_or_payment(): void
    {
        Mail::fake();
        $user = $this->tailor();
        Sanctum::actingAs($user);
        $customerId = (string) Str::uuid();
        $paymentId = (string) Str::uuid();

        $this->postJson('/api/customers', [
            'id' => $customerId,
            'name' => 'Bola',
            'phone' => '08031111111',
        ])->assertCreated()->assertJsonPath('data.id', $customerId);

        $this->postJson('/api/customers', [
            'id' => $customerId,
            'name' => 'Bola',
            'phone' => '08031111111',
        ])->assertOk()->assertJsonPath('data.id', $customerId);

        $this->assertSame(1, Customer::query()->where('phone', '08031111111')->count());

        $payload = [
            'id' => $paymentId,
            'amount' => '5000',
            'paid_on' => '2026-11-07',
            'method' => 'cash',
        ];

        $this->postJson("/api/customers/{$customerId}/payments", $payload)->assertCreated();
        $this->postJson("/api/customers/{$customerId}/payments", $payload)->assertOk();

        $this->assertSame(1, Payment::query()->whereKey($paymentId)->count());
        Mail::assertSent(ShopNoticeMail::class, 1);
    }

    public function test_a_shop_cannot_reuse_another_shops_client_id(): void
    {
        $owner = $this->tailor();
        $other = $this->tailor('other@example.com', 'Other Shop');
        $customer = Customer::factory()->create([
            'business_id' => $owner->business->id,
            'phone' => '08032222222',
        ]);

        Sanctum::actingAs($other);

        $this->postJson('/api/customers', [
            'id' => $customer->id,
            'name' => 'Copied',
            'phone' => '08033333333',
        ])->assertNotFound();

        $this->assertDatabaseMissing('customers', [
            'business_id' => $other->business->id,
            'name' => 'Copied',
        ]);
        $this->assertSame($owner->business->id, $customer->fresh()->business_id);
    }

    public function test_repeating_a_job_edit_keeps_one_previous_version(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create(['business_id' => $user->business->id]);
        Sanctum::actingAs($user);
        $key = (string) Str::uuid();

        $jobId = $this->postJson("/api/customers/{$customer->id}/jobs", [
            'title' => 'Gown',
            'agreed_amount' => '10000',
            'service_date' => '2026-11-20',
        ])->assertCreated()->json('data.id');

        $edit = [
            'title' => 'Gown',
            'agreed_amount' => '12000',
            'service_date' => '2026-11-21',
        ];

        $this->putJson("/api/customers/{$customer->id}/jobs/{$jobId}", $edit, [
            'Idempotency-Key' => $key,
        ])->assertOk()->assertJsonPath('data.agreed_amount', '12000.00');

        $this->putJson("/api/customers/{$customer->id}/jobs/{$jobId}", [
            'title' => 'Gown',
            'agreed_amount' => '99000',
            'service_date' => '2026-12-01',
        ], [
            'Idempotency-Key' => $key,
        ])->assertOk()->assertJsonPath('data.agreed_amount', '12000.00');

        $this->assertSame(1, RecordRevision::query()->where('record_id', $jobId)->count());
        $this->assertSame('10000.00', RecordRevision::query()->where('record_id', $jobId)->firstOrFail()->previous['agreed_amount']);
    }

    public function test_a_lost_insert_race_returns_the_saved_customer(): void
    {
        $user = $this->tailor();
        $id = (string) Str::uuid();

        $saved = app(SyncRecords::class)->insertOrReplay(
            Customer::class,
            $user->business->id,
            $id,
            function () use ($user, $id) {
                Customer::factory()->create([
                    'id' => $id,
                    'client_uuid' => $id,
                    'business_id' => $user->business->id,
                    'name' => 'Bola',
                    'phone' => '08037777777',
                ]);

                throw new UniqueConstraintViolationException(
                    'mysql',
                    'insert into customers',
                    [],
                    new PDOException('Duplicate entry'),
                );
            },
        );

        $this->assertSame($id, (string) $saved->id);
        $this->assertSame('Bola', $saved->name);
        $this->assertSame(1, Customer::query()->whereKey($id)->count());
    }

    public function test_a_removed_client_id_is_not_created_again(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create([
            'business_id' => $user->business->id,
            'phone' => '08035555555',
        ]);
        $customer->delete();
        Sanctum::actingAs($user);

        $this->postJson('/api/customers', [
            'id' => $customer->id,
            'name' => 'Bola',
            'phone' => '08036666666',
        ])->assertStatus(409);

        $this->assertSame(0, Customer::query()->where('phone', '08036666666')->count());
    }

    public function test_a_rejected_measurement_is_not_stored(): void
    {
        $user = $this->tailor();
        $customer = Customer::factory()->create(['business_id' => $user->business->id]);
        Sanctum::actingAs($user);
        $id = (string) Str::uuid();

        $this->postJson("/api/customers/{$customer->id}/measurements", [
            'id' => $id,
            'template_slug' => 'female',
            'taken_on' => '2026-11-07',
            'unit' => 'cm',
            'values' => [],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('measurements', ['id' => $id]);
    }

    public function test_an_expense_id_is_saved_once(): void
    {
        $user = $this->tailor();
        $category = ExpenseCategory::query()->where('slug', 'fabric')->firstOrFail();
        Sanctum::actingAs($user);
        $id = (string) Str::uuid();
        $payload = [
            'id' => $id,
            'amount' => '2500',
            'spent_on' => '2026-11-07',
            'expense_category_id' => $category->id,
            'method' => 'cash',
            'description' => 'Ankara',
        ];

        $this->postJson('/api/expenses', $payload)->assertCreated();
        $this->postJson('/api/expenses', $payload)->assertOk();

        $this->assertDatabaseCount('expenses', 1);
    }

    private function tailor(string $email = 'ada@example.com', string $shop = 'Ada Atelier'): User
    {
        $user = User::factory()->create([
            'name' => 'Ada',
            'email' => $email,
        ]);

        Business::factory()->create([
            'user_id' => $user->id,
            'name' => $shop,
            'phone' => '08030000000',
            'country' => 'NG',
        ]);

        return $user->fresh(['business']);
    }
}
