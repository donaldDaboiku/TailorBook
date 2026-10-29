<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReferralActivity extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'kind',
        'referrer_business_id',
        'salesperson_id',
        'referred_business_id',
        'reward_id',
        'summary',
    ];
}
