<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Services\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $country = $this->business?->country
            ?? $request->user()?->business?->country
            ?? 'NG';

        $whatsappPhone = $this->whatsapp_phone ?: $this->phone;

        return [
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
    }
}
