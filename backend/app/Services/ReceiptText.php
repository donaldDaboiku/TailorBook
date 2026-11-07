<?php

namespace App\Services;

use App\Http\Controllers\Api\BusinessController;
use App\Mail\ShopNoticeMail;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
        $address = $business?->address;
        $email = $business?->user?->email;
        $slogan = $business?->receipt_header;

        $name = $customer?->name ?: 'Customer';
        $outstanding = $customer
            ? Money::format($customer->outstandingAmount(), $currency)
            : null;
        $lines = [
            "Hello {$name}, this is {$shop}.",
            "Receipt {$number}.",
            "Amount paid: {$amount}.",
        ];

        if (filled($address)) {
            $lines[] = "Address: {$address}.";
        }

        if (filled($email)) {
            $lines[] = "Email: {$email}.";
        }

        if (filled($slogan)) {
            $lines[] = $slogan;
        }

        if (filled($payment->job?->title)) {
            $lines[] = 'Job: '.$payment->job->title.'.';
        }

        if ($outstanding !== null) {
            $lines[] = "Outstanding: {$outstanding}.";
        }

        return [
            'receipt_number' => $number,
            'shop_name' => $shop,
            'shop_phone' => $business?->phone,
            'shop_address' => filled($address) ? $address : null,
            'shop_email' => filled($email) ? $email : null,
            'header' => filled($slogan) ? $slogan : null,
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
            'outstanding_label' => $outstanding,
            'caption' => implode(' ', $lines),
        ];
    }

    public static function sendToAccount(Payment $payment, ?string $image = null): bool
    {
        $payment->loadMissing(['business.user']);
        $email = trim((string) $payment->business?->user?->email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $document = self::document($payment);
        $number = $document['receipt_number'];
        $shop = $document['shop_name'];

        try {
            Mail::to($email)->send(new ShopNoticeMail(
                "Receipt {$number} from {$shop}",
                (string) $document['caption'],
                is_string($image) && $image !== '' ? $image : null,
                is_string($image) && $image !== '' ? "{$number}.png" : null,
            ));
        } catch (Throwable $exception) {
            Log::warning('Receipt email failed.', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
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
