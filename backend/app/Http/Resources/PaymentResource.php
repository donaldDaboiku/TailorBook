<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $request->user()?->business?->currency ?? 'NGN';
        $amount = (string) $this->amount;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_job_id' => $this->customer_job_id,
            'job_title' => $this->whenLoaded('job', fn () => $this->job?->title),
            'amount' => $amount,
            'amount_label' => Money::format($amount, $currency),
            'paid_on' => $this->paid_on?->toDateString(),
            'method' => $this->method?->value,
            'method_label' => match ($this->method?->value) {
                'cash' => 'Cash',
                'bank_transfer' => 'Bank transfer',
                'pos' => 'POS',
                'other' => 'Other',
                default => null,
            },
            'reference' => $this->reference,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
