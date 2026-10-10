<?php

namespace App\Services;

use App\Models\Payment;

class ReceiptText
{
    public static function for(Payment $payment): string
    {
        $payment->loadMissing(['business', 'customer', 'job']);
        $business = $payment->business;
        $customer = $payment->customer;
        $currency = $business?->currency ?? 'NGN';

        $lines = array_values(array_filter([
            $business?->name,
            filled($business?->receipt_header) ? $business->receipt_header : null,
            filled($business?->phone) ? 'Tel: '.$business->phone : null,
            '',
            'RECEIPT',
            'Date: '.($payment->paid_on?->toDateString() ?? ''),
            'Customer: '.($customer?->name ?? ''),
            $payment->job ? 'Job: '.$payment->job->title : null,
            'Amount: '.Money::format((string) $payment->amount, $currency),
            'Method: '.self::methodLabel($payment->method?->value),
            filled($payment->reference) ? 'Reference: '.$payment->reference : null,
            filled($payment->note) ? 'Note: '.$payment->note : null,
        ], fn ($line) => $line !== null));

        if ($customer !== null) {
            $lines[] = '';
            $lines[] = 'Agreed: '.Money::format($customer->totalAgreed(), $currency);
            $lines[] = 'Paid: '.Money::format($customer->totalPaid(), $currency);
            $lines[] = 'Outstanding: '.Money::format($customer->outstandingAmount(), $currency);
        }

        if (filled($business?->receipt_footer)) {
            $lines[] = '';
            $lines[] = $business->receipt_footer;
        }

        return implode("\n", $lines);
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
