<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_reference_data_covers_the_three_templates_and_expense_categories(): void
    {
        $this->assertSame(
            ['Bust', 'Waist', 'Hip', 'Shoulder', 'Sleeve Length', 'Armhole', 'Neck', 'Blouse Length', 'Gown Length', 'Skirt Length', 'Trouser Length', 'Thigh', 'Knee', 'Ankle'],
            MeasurementTemplate::query()->where('slug', 'female')->firstOrFail()->fields->pluck('label')->all(),
        );
        $this->assertSame(
            ['Neck', 'Shoulder', 'Chest', 'Stomach', 'Waist', 'Hip', 'Sleeve Length', 'Biceps', 'Shirt Length', 'Trouser Length', 'Thigh', 'Knee', 'Inseam', 'Outseam'],
            MeasurementTemplate::query()->where('slug', 'male')->firstOrFail()->fields->pluck('label')->all(),
        );
        $this->assertSame(
            ['Chest', 'Waist', 'Hip', 'Shoulder', 'Sleeve', 'Length', 'Trouser length', 'Neck'],
            MeasurementTemplate::query()->where('slug', 'child')->firstOrFail()->fields->pluck('label')->all(),
        );
        $this->assertSame(10, ExpenseCategory::query()->whereNull('business_id')->count());
    }

    public function test_a_user_has_one_business(): void
    {
        $user = User::factory()->create();

        Business::query()->create([
            'user_id' => $user->id,
            'name' => 'Ada Atelier',
            'phone' => '08030000000',
        ]);

        $this->expectException(QueryException::class);

        Business::query()->create([
            'user_id' => $user->id,
            'name' => 'Second shop',
            'phone' => '08031111111',
        ]);
    }

    public function test_phone_numbers_are_unique_per_business_until_the_customer_is_archived(): void
    {
        $first = $this->business();
        $second = $this->business();

        $customer = Customer::query()->create([
            'business_id' => $first->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        Customer::query()->create([
            'business_id' => $second->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        $customer->delete();

        $replacement = Customer::query()->create([
            'business_id' => $first->id,
            'name' => 'Grace Okonkwo',
            'phone' => '08031234567',
        ]);

        $this->assertNotSame($customer->id, $replacement->id);

        $this->expectException(QueryException::class);

        Customer::query()->create([
            'business_id' => $first->id,
            'name' => 'Another Grace',
            'phone' => '08031234567',
        ]);
    }

    public function test_outstanding_is_calculated_and_archived_rows_do_not_count(): void
    {
        $this->assertFalse(Schema::hasColumn('customers', 'balance'));
        $this->assertFalse(Schema::hasColumn('customer_jobs', 'balance'));
        $this->assertFalse(Schema::hasColumn('customer_jobs', 'outstanding'));

        $business = $this->business();
        $customer = Customer::query()->create([
            'business_id' => $business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        $gown = CustomerJob::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'title' => 'Gown',
            'agreed_amount' => '150000.00',
            'service_date' => '2026-10-03',
        ]);

        Payment::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'customer_job_id' => $gown->id,
            'amount' => '50000.00',
            'paid_on' => '2026-10-03',
            'method' => PaymentMethod::BankTransfer,
        ]);

        $archived = Payment::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'customer_job_id' => $gown->id,
            'amount' => '20000.00',
            'paid_on' => '2026-10-03',
            'method' => PaymentMethod::Cash,
        ]);
        $archived->delete();

        Payment::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'amount' => '10000.00',
            'paid_on' => '2026-10-03',
            'method' => PaymentMethod::Pos,
        ]);

        $this->assertSame('100000.00', $gown->outstandingAmount());
        $this->assertSame('90000.00', $customer->fresh()->outstandingAmount());

        $gown->delete();

        $this->assertSame('-10000.00', $customer->fresh()->outstandingAmount());
    }

    public function test_zero_payment_is_rejected(): void
    {
        $business = $this->business();
        $customer = Customer::query()->create([
            'business_id' => $business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        $this->expectException(QueryException::class);

        Payment::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'amount' => '0.00',
            'paid_on' => '2026-10-03',
            'method' => PaymentMethod::Cash,
        ]);
    }

    public function test_a_new_measurement_does_not_replace_the_previous_one(): void
    {
        $business = $this->business();
        $customer = Customer::query()->create([
            'business_id' => $business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);
        $template = MeasurementTemplate::query()->where('slug', 'female')->firstOrFail();
        $bust = $template->fields()->where('key', 'bust')->firstOrFail();

        $older = Measurement::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'measurement_template_id' => $template->id,
            'taken_on' => '2026-03-14',
        ]);
        $older->values()->create([
            'measurement_field_id' => $bust->id,
            'value_cm' => '93.98',
        ]);

        $newer = Measurement::query()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'measurement_template_id' => $template->id,
            'taken_on' => '2026-10-03',
        ]);
        $newer->values()->create([
            'measurement_field_id' => $bust->id,
            'value_cm' => '96.52',
        ]);

        $this->assertSame(2, $customer->measurements()->count());
        $this->assertSame('93.98', $older->values()->firstOrFail()->value_cm);
        $this->assertSame('96.52', $newer->values()->firstOrFail()->value_cm);

        $older->values()->firstOrFail()->update(['value_cm' => '10.00']);

        $this->assertSame('93.98', $older->values()->firstOrFail()->value_cm);
    }

    public function test_client_uuid_defaults_to_the_record_id(): void
    {
        $business = $this->business();
        $customer = Customer::query()->create([
            'business_id' => $business->id,
            'name' => 'Grace',
            'phone' => '08031234567',
        ]);

        $this->assertTrue(Str::isUuid($customer->id));
        $this->assertSame($customer->id, $customer->client_uuid);

        $sent = (string) Str::uuid();
        $synced = Customer::query()->create([
            'client_uuid' => $sent,
            'business_id' => $business->id,
            'name' => 'Chinedu',
            'phone' => '08037654321',
        ]);

        $this->assertSame($sent, $synced->client_uuid);
        $this->assertNotSame($sent, $synced->id);

        $category = ExpenseCategory::query()->where('slug', 'fabric')->firstOrFail();

        Expense::query()->create([
            'business_id' => $business->id,
            'expense_category_id' => $category->id,
            'amount' => '4500.00',
            'spent_on' => '2026-10-03',
            'description' => 'Ankara',
            'method' => PaymentMethod::Cash,
        ]);

        $this->assertSame('4500.00', $business->expenses()->firstOrFail()->amount);
    }

    private function business(): Business
    {
        return Business::query()->create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Ada Atelier',
            'phone' => '08030000000',
        ]);
    }
}
