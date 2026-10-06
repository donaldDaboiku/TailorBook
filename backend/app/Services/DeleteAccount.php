<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAccount
{
    public static function run(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $business = $user->business;

            if ($business instanceof Business) {
                self::purgeBusiness($business);
            }

            $user->tokens()->delete();
            $user->delete();
        });
    }

    private static function purgeBusiness(Business $business): void
    {
        $businessId = $business->id;

        Payment::withTrashed()->where('business_id', $businessId)->forceDelete();
        Measurement::withTrashed()->where('business_id', $businessId)->forceDelete();
        CustomerJob::withTrashed()->where('business_id', $businessId)->forceDelete();
        Expense::withTrashed()->where('business_id', $businessId)->forceDelete();
        Customer::withTrashed()->where('business_id', $businessId)->forceDelete();

        MeasurementTemplate::query()->where('business_id', $businessId)->delete();
        ExpenseCategory::query()->where('business_id', $businessId)->delete();

        $business->delete();
    }
}
