<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['measurement_template_id', 'key', 'label', 'sort_order'])]
class MeasurementField extends Model
{
    use HasUuids;

    public function template(): BelongsTo
    {
        return $this->belongsTo(MeasurementTemplate::class, 'measurement_template_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MeasurementValue::class);
    }
}
