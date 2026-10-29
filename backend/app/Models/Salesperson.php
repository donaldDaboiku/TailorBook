<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salesperson extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(Business::class, 'referred_by_salesperson_id');
    }
}
