<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function valueFor(string $key): ?string
    {
        $value = static::query()->whereKey($key)->value('value');

        return is_string($value) ? $value : null;
    }
}
