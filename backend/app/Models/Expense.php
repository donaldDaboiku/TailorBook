<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\AssignsClientUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id',
    'client_uuid',
    'business_id',
    'expense_category_id',
    'amount',
    'spent_on',
    'description',
    'method',
    'note',
])]
class Expense extends Model
{
    use AssignsClientUuid, HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
