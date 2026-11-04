<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['scope_key'])]
#[Fillable(['business_id', 'slug', 'name', 'sort_order'])]
class MeasurementTemplate extends Model
{
    use HasUuids;

    public const SYSTEM_SCOPE = '00000000-0000-0000-0000-000000000000';

    protected static function booted(): void
    {
        static::saving(function (MeasurementTemplate $template): void {
            $template->scope_key = $template->business_id ?? self::SYSTEM_SCOPE;
        });
    }

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
