<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RecordRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'business_id',
        'record_type',
        'record_id',
        'previous',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous' => 'array',
        ];
    }
}
