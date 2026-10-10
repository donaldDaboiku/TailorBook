<?php

namespace App\Services;

use App\Http\Controllers\Api\BusinessController;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ReceiptText
{
    /**
     * @return array<string, mixed>
     */
    public static function document(Payment $payment): array
    {
        $payment->loadMissing(['business.user', 'customer', 'job']);
        $business = $payment->business;
        $customer = $payment->customer;
        $currency = $business?->currency ?? 'NGN';
        $number = self::ensureNumber($payment);
        $amount = Money::format((string) $payment->amount, $currency);
        $shop = $business?->name ?? 'Shop';

        return [
            'receipt_number' => $number,
            'shop_name' => $shop,
            'shop_phone' => $business?->phone,
            'header' => $business?->receipt_header,
            'footer' => $business?->receipt_footer,
            'logo_data_url' => BusinessController::logoDataUrl($business),
            'signature_name' => filled($business?->signature_name)
                ? $business->signature_name
                : ($business?->user?->name ?? $shop),
            'date' => $payment->paid_on?->toDateString(),
            'customer' => $customer?->name,
            'job' => $payment->job?->title,
            'amount_label' => $amount,
            'method_label' => self::methodLabel($payment->method?->value),
            'reference' => $payment->reference,
            'note' => $payment->note,
            'agreed_label' => $customer ? Money::format($customer->totalAgreed(), $currency) : null,
            'paid_label' => $customer ? Money::format($customer->totalPaid(), $currency) : null,
            'outstanding_label' => $customer ? Money::format($customer->outstandingAmount(), $currency) : null,
            'caption' => "Receipt {$number} from {$shop}. Amount {$amount}.",
        ];
    }

    public static function ensureNumber(Payment $payment): string
    {
        if (filled($payment->receipt_number)) {
            return (string) $payment->receipt_number;
        }

        return DB::transaction(function () use ($payment) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (filled($locked->receipt_number)) {
                $payment->receipt_number = $locked->receipt_number;

                return (string) $locked->receipt_number;
            }

            $next = Payment::query()
                ->where('business_id', $locked->business_id)
                ->whereNotNull('receipt_number')
                ->count() + 1;

            $number = 'RCP-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $locked->forceFill(['receipt_number' => $number])->save();
            $payment->receipt_number = $number;

            return $number;
        });
    }

    private static function methodLabel(?string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank transfer',
            'pos' => 'POS',
            'other' => 'Other',
            default => '—',
        };
    }
}
