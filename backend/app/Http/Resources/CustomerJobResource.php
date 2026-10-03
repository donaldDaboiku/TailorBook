<?php

namespace App\Http\Resources;

use App\Models\CustomerJob;
use App\Services\Balance;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerJob
 */
class CustomerJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $request->user()?->business?->currency ?? 'NGN';
        $agreed = (string) $this->agreed_amount;
        $outstanding = $this->outstandingAmount();
        $paid = Balance::outstanding([$agreed], [$outstanding]);

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'title' => $this->title,
            'agreed_amount' => $agreed,
            'agreed_amount_label' => Money::format($agreed, $currency),
            'paid_amount' => $paid,
            'paid_amount_label' => Money::format($paid, $currency),
            'outstanding' => $outstanding,
            'outstanding_label' => Money::format($outstanding, $currency),
            'service_date' => $this->service_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
