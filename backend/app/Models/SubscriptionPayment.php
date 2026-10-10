<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'reference',
        'amount',
        'currency',
        'channel',
        'gateway_response',
        'failure_message',
        'status',
        'paid_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amountLabel(): string
    {
        $naira = number_format(((int) $this->amount) / 100, 2, '.', ',');

        return "₦{$naira}";
    }
}
