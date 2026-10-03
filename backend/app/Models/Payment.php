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
    'customer_id',
    'customer_job_id',
    'amount',
    'paid_on',
    'method',
    'reference',
    'note',
])]
class Payment extends Model
{
    use AssignsClientUuid, HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
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

    public function job(): BelongsTo
    {
        return $this->belongsTo(CustomerJob::class, 'customer_job_id');
    }
}
