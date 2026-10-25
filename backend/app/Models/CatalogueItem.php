<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'price',
        'image_path',
        'image_back_path',
        'image_side_path',
    ];

    /**
     * @return list<string>
     */
    public function storedImages(): array
    {
        return array_values(array_filter([
            $this->image_path,
            $this->image_back_path,
            $this->image_side_path,
        ], fn (mixed $path): bool => is_string($path) && $path !== ''));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
