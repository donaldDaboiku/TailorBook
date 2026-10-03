<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Services\Money;
use App\Services\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public bool $includeFinance = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $country = $this->business?->country
            ?? $request->user()?->business?->country
            ?? 'NG';

        $currency = $this->business?->currency
            ?? $request->user()?->business?->currency
            ?? 'NGN';

        $whatsappPhone = $this->whatsapp_phone ?: $this->phone;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'whatsapp_phone' => $whatsappPhone,
            'email' => $this->email,
            'gender' => $this->gender?->value,
            'address' => $this->address,
            'notes' => $this->notes,
            'whatsapp_url' => PhoneNormalizer::whatsappUrl($whatsappPhone, $country),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->includeFinance) {
            $agreed = $this->totalAgreed();
            $paid = $this->totalPaid();
            $outstanding = $this->outstandingAmount();

            $data['finance'] = [
                'currency' => $currency,
                'total_agreed' => $agreed,
                'total_paid' => $paid,
                'outstanding' => $outstanding,
                'total_agreed_label' => Money::format($agreed, $currency),
                'total_paid_label' => Money::format($paid, $currency),
                'outstanding_label' => Money::format($outstanding, $currency),
            ];
        }

        return $data;
    }
}
