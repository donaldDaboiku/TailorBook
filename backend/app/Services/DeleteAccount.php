<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\Business;
use App\Models\BusinessMeasurementFieldPref;
use App\Models\CatalogueItem;
use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeedbackMessage;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
use App\Models\Payment;
use App\Models\ReferralActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAccount
{
    public static function run(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $business = $user->business;

            if ($business instanceof Business) {
                self::purgeBusiness($business);
            }

            FeedbackMessage::query()->where('user_id', $user->id)->delete();
            AdminAction::query()->where('target_user_id', $user->id)->update([
                'target_user_id' => null,
                'target_email' => null,
                'reason' => 'Shop account deleted.',
            ]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }

    private static function purgeBusiness(Business $business): void
    {
        $businessId = $business->id;

        ReferralActivity::query()
            ->where('referrer_business_id', $businessId)
            ->orWhere('referred_business_id', $businessId)
            ->update(['summary' => 'A shop record was deleted.']);

        if (filled($business->logo_path)) {
            Storage::disk('local')->delete($business->logo_path);
        }

        Payment::withTrashed()->where('business_id', $businessId)->forceDelete();
        Measurement::withTrashed()->where('business_id', $businessId)->forceDelete();
        CustomerJob::withTrashed()->where('business_id', $businessId)->forceDelete();
        Expense::withTrashed()->where('business_id', $businessId)->forceDelete();
        Customer::withTrashed()->where('business_id', $businessId)->forceDelete();

        BusinessMeasurementFieldPref::query()->where('business_id', $businessId)->delete();
        MeasurementTemplate::query()->where('business_id', $businessId)->delete();
        ExpenseCategory::query()->where('business_id', $businessId)->delete();

        CatalogueItem::query()
            ->where('business_id', $businessId)
            ->get()
            ->each(function (CatalogueItem $item): void {
                foreach ($item->storedImages() as $path) {
                    Storage::disk('local')->delete($path);
                }
                $item->delete();
            });

        $business->delete();
    }
}
