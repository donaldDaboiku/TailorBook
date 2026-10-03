<?php

namespace App\Models;

use App\Models\Concerns\AssignsClientUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id',
    'client_uuid',
    'business_id',
    'customer_id',
    'measurement_template_id',
    'taken_on',
])]
class Measurement extends Model
{
    use AssignsClientUuid, HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::updating(function (Measurement $measurement): bool {
            return ! $measurement->isDirty([
                'customer_id',
                'business_id',
                'measurement_template_id',
                'taken_on',
                'client_uuid',
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_on' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MeasurementTemplate::class, 'measurement_template_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MeasurementValue::class);
    }
}
