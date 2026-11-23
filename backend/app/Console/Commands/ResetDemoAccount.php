<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessMeasurementFieldPref;
use App\Models\CatalogueItem;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Measurement;
use App\Models\MeasurementField;
use App\Models\MeasurementTemplate;
use App\Models\MeasurementValue;
use App\Models\Payment;
use App\Models\RecordRevision;
use App\Models\ShopReview;
use App\Models\SubscriptionPayment;
use App\Models\SyncAction;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('demo:reset')]
#[Description('Create the configured demo account and refresh its sample records')]
class ResetDemoAccount extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('is_demo_account', true)->first();

        if (! $user instanceof User) {
            $user = $this->createDemoAccount();

            if (! $user instanceof User) {
                return self::FAILURE;
            }
        }

        $user->forceFill([
            'name' => 'TailorMate Demo Shop',
            'role' => UserRole::Tailor,
            'subscription_status' => SubscriptionStatus::Free,
            'subscribed_until' => null,
            'is_demo_account' => true,
        ])->save();

        $business = Business::query()->firstOrNew(['user_id' => $user->id]);
        $business->forceFill([
            'name' => 'Demo Fashion House',
            'phone' => '+12025550100',
            'whatsapp_phone' => null,
            'country' => 'US',
            'state' => 'California',
            'address' => '100 Sample Street',
            'measurement_unit' => 'in',
            'currency' => 'USD',
            'receipt_header' => null,
            'receipt_footer' => null,
            'signature_name' => null,
        ])->save();

        app(ReferenceDataSeeder::class)->run();
        $this->refreshRecords($user, $business);

        $this->info("Demo account is ready: {$user->email}");

        return self::SUCCESS;
    }

    private function createDemoAccount(): ?User
    {
        $email = config('services.demo.email');
        $password = config('services.demo.password');

        if (
            ! is_string($email)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! is_string($password)
            || strlen($password) < 14
        ) {
            $this->error('Set DEMO_ACCOUNT_EMAIL and a DEMO_ACCOUNT_PASSWORD of at least 14 characters before provisioning.');

            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User && ! $user->isDemoAccount()) {
            $this->error('The configured demo email already belongs to a non-demo account.');

            return null;
        }

        $user ??= new User;
        $user->forceFill([
            'name' => 'TailorMate Demo Shop',
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Tailor,
            'subscription_status' => SubscriptionStatus::Free,
            'subscribed_until' => null,
            'is_demo_account' => true,
        ])->save();

        return $user;
    }

    private function refreshRecords(User $user, Business $business): void
    {
        $femaleTemplate = MeasurementTemplate::query()
            ->whereNull('business_id')
            ->where('slug', 'female')
            ->firstOrFail();

        $fabricCategory = ExpenseCategory::query()
            ->whereNull('business_id')
            ->where('slug', 'fabric')
            ->firstOrFail();

        DB::transaction(function () use ($business, $fabricCategory, $femaleTemplate, $user): void {
            Payment::withTrashed()->where('business_id', $business->id)->forceDelete();
            CustomerJob::withTrashed()->where('business_id', $business->id)->forceDelete();
            Measurement::withTrashed()->where('business_id', $business->id)->forceDelete();
            Expense::withTrashed()->where('business_id', $business->id)->forceDelete();
            Customer::withTrashed()->where('business_id', $business->id)->forceDelete();

            CatalogueItem::query()->where('business_id', $business->id)->delete();
            ShopReview::query()->where('business_id', $business->id)->delete();
            BusinessMeasurementFieldPref::query()->where('business_id', $business->id)->delete();
            MeasurementTemplate::query()->where('business_id', $business->id)->delete();
            ExpenseCategory::query()->where('business_id', $business->id)->delete();
            SyncAction::query()->where('business_id', $business->id)->delete();
            RecordRevision::query()->where('business_id', $business->id)->delete();
            SubscriptionPayment::query()->where('user_id', $user->id)->delete();

            $customers = collect([
                ['name' => 'Amina Bello', 'gender' => 'female'],
                ['name' => 'Chidi Okafor', 'gender' => 'male'],
                ['name' => 'Zara Adeyemi', 'gender' => 'female'],
                ['name' => 'Tunde Bakare', 'gender' => 'male'],
                ['name' => 'Nneka Eze', 'gender' => 'female'],
                ['name' => 'Lola Mensah', 'gender' => 'female'],
                ['name' => 'Kofi Agyeman', 'gender' => 'male'],
            ])->map(fn (array $sample, int $index) => Customer::query()->create([
                ...$sample,
                'business_id' => $business->id,
                'phone' => '+120255501'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'whatsapp_phone' => '+120255501'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'email' => 'customer'.($index + 1).'@example.invalid',
                'address' => 'Sample District',
                'notes' => 'Fictional demo customer.',
            ]))->values();

            foreach ([0, 2] as $customerIndex) {
                $measurement = Measurement::query()->create([
                    'business_id' => $business->id,
                    'customer_id' => $customers[$customerIndex]->id,
                    'measurement_template_id' => $femaleTemplate->id,
                    'taken_on' => now()->subDays($customerIndex)->toDateString(),
                ]);

                foreach ([
                    'bust' => 92 + ($customerIndex * 2),
                    'waist' => 74 + $customerIndex,
                    'hip' => 98 + ($customerIndex * 2),
                    'shoulder' => 39 + $customerIndex,
                ] as $key => $value) {
                    $field = MeasurementField::query()
                        ->where('measurement_template_id', $femaleTemplate->id)
                        ->where('key', $key)
                        ->firstOrFail();

                    MeasurementValue::query()->create([
                        'measurement_id' => $measurement->id,
                        'measurement_field_id' => $field->id,
                        'value_cm' => $value,
                    ]);
                }
            }

            $jobs = [
                ['customer' => 0, 'title' => 'Custom coat', 'days' => 14, 'amount' => 850, 'status' => 'pending'],
                ['customer' => 1, 'title' => 'Suit fitting', 'days' => 0, 'amount' => 1200, 'status' => 'in_progress'],
                ['customer' => 2, 'title' => 'Ceremony dress', 'days' => -14, 'amount' => 950, 'status' => 'completed'],
            ];

            foreach ($jobs as $sample) {
                $job = CustomerJob::query()->create([
                    'business_id' => $business->id,
                    'customer_id' => $customers[$sample['customer']]->id,
                    'title' => $sample['title'],
                    'agreed_amount' => $sample['amount'],
                    'service_date' => now()->addDays($sample['days'])->toDateString(),
                    'status' => $sample['status'],
                ]);

                Payment::query()->create([
                    'business_id' => $business->id,
                    'customer_id' => $customers[$sample['customer']]->id,
                    'customer_job_id' => $job->id,
                    'amount' => $sample['status'] === 'completed' ? $sample['amount'] : 200,
                    'paid_on' => now()->subDays(1)->toDateString(),
                    'method' => 'cash',
                    'reference' => null,
                    'note' => 'Sample demo transaction - no real payment.',
                ]);
            }

            foreach ([
                ['amount' => 180, 'description' => 'Sample fabric purchase'],
                ['amount' => 45, 'description' => 'Sample studio transport'],
                ['amount' => 90, 'description' => 'Sample notions and thread'],
            ] as $sample) {
                Expense::query()->create([
                    'business_id' => $business->id,
                    'expense_category_id' => $fabricCategory->id,
                    'amount' => $sample['amount'],
                    'spent_on' => now()->subDays(2)->toDateString(),
                    'description' => $sample['description'],
                    'method' => 'cash',
                    'note' => 'Fictional demo expense.',
                ]);
            }
        });
    }
}
