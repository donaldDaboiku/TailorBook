<?php

namespace App\Http\Resources;

use App\Enums\MeasurementUnit;
use App\Models\Measurement;
use App\Services\Units;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Measurement
 */
class MeasurementResource extends JsonResource
{
    public MeasurementUnit $displayUnit = MeasurementUnit::Inches;

    public ?Measurement $previous = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $values = $this->whenLoaded('values', function () {
            return $this->values
                ->sortBy(fn ($value) => $value->field?->sort_order ?? 0)
                ->map(function ($value) {
                    $row = [
                        'field_id' => $value->measurement_field_id,
                        'key' => $value->field?->key,
                        'label' => $value->field?->label,
                        'value_cm' => (string) $value->value_cm,
                        'value' => Units::fromCm((string) $value->value_cm, $this->displayUnit),
                    ];

                    if ($this->previous) {
                        $previousValue = $this->previous->values
                            ->firstWhere('measurement_field_id', $value->measurement_field_id);

                        if ($previousValue) {
                            $row['previous_value'] = Units::fromCm((string) $previousValue->value_cm, $this->displayUnit);
                            $row['change'] = Units::change(
                                (string) $value->value_cm,
                                (string) $previousValue->value_cm,
                                $this->displayUnit,
                            );
                        }
                    }

                    return $row;
                })
                ->values();
        });

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'taken_on' => $this->taken_on?->toDateString(),
            'unit' => $this->displayUnit->value,
            'template' => [
                'id' => $this->template?->id,
                'slug' => $this->template?->slug,
                'name' => $this->template?->name,
            ],
            'values' => $values,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
