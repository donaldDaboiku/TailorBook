<?php

namespace App\Models;

use App\Models\Concerns\AssignsClientUuid;
use App\Services\Balance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id',
    'client_uuid',
    'business_id',
    'customer_id',
    'title',
    'agreed_amount',
    'service_date',
])]
class CustomerJob extends Model
{
    use AssignsClientUuid, HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreed_amount' => 'decimal:2',
            'service_date' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function outstandingAmount(): string
    {
        return Balance::outstanding(
            [(string) $this->agreed_amount],
            $this->payments()->pluck('amount')->all(),
        );
    }
}
