<?php

namespace App\Models;

use App\Enums\Gender;
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
    'name',
    'phone',
    'whatsapp_phone',
    'email',
    'gender',
    'address',
    'notes',
])]
class Customer extends Model
{
    use AssignsClientUuid, HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(CustomerJob::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function outstandingAmount(): string
    {
        return Balance::outstanding(
            $this->jobs()->pluck('agreed_amount')->all(),
            $this->payments()
                ->where(function ($query) {
                    $query->whereNull('customer_job_id')->orWhereHas('job');
                })
                ->pluck('amount')
                ->all(),
        );
    }
}
