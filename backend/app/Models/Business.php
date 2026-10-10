<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'phone', 'whatsapp_phone', 'country', 'measurement_unit', 'currency', 'receipt_header', 'receipt_footer', 'logo_path', 'signature_name'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measurement_unit' => MeasurementUnit::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerJobs(): HasMany
    {
        return $this->hasMany(CustomerJob::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function measurementFieldPrefs(): HasMany
    {
        return $this->hasMany(BusinessMeasurementFieldPref::class);
    }
}
