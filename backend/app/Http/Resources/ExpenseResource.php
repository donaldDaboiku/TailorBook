<?php

namespace App\Http\Resources;

use App\Models\Expense;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
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
            'amount' => $amount,
            'amount_label' => Money::format($amount, $currency),
            'spent_on' => $this->spent_on?->toDateString(),
            'description' => $this->description,
            'method' => $this->method?->value,
            'method_label' => match ($this->method?->value) {
                'cash' => 'Cash',
                'bank_transfer' => 'Bank transfer',
                'pos' => 'POS',
                'other' => 'Other',
                default => null,
            },
            'note' => $this->note,
            'category' => [
                'id' => $this->category?->id,
                'slug' => $this->category?->slug,
                'name' => $this->category?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
