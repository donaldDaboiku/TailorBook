<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['measurement_id', 'measurement_field_id', 'value_cm'])]
class MeasurementValue extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (MeasurementValue $value): bool {
            return ! $value->isDirty(['value_cm', 'measurement_field_id', 'measurement_id']);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_cm' => 'decimal:2',
        ];
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(MeasurementField::class, 'measurement_field_id');
    }
}
