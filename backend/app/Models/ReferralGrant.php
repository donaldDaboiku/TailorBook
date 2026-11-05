<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralGrant extends Model
{
    use HasUuids;

    protected $fillable = [
        'referrer_business_id',
        'milestone',
        'status',
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
            'milestone' => 'integer',
            'approved_at' => 'datetime',
            'granted_until' => 'date',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'referrer_business_id');
    }
}
