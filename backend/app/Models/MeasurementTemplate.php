<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'slug', 'name', 'sort_order'])]
class MeasurementTemplate extends Model
{
    use HasUuids;

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(MeasurementField::class)->orderBy('sort_order');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }
}
