<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SyncAction extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_id',
        'idempotency_key',
        'subject_type',
        'subject_id',
    ];
}
