<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    use HasUuids;

    protected $fillable = [
        'referrer_business_id',
        'referred_business_id',
        'status',
        'paid_at',
        'approved_at',
        'approved_by',
        'granted_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'granted_until' => 'date',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'referrer_business_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'referred_business_id');
    }
}
