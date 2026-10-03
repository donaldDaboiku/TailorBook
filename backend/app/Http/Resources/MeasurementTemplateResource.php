<?php

namespace App\Http\Resources;

use App\Models\MeasurementTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeasurementTemplate
 */
class MeasurementTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'fields' => $this->whenLoaded('fields', fn () => $this->fields->map(fn ($field) => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'sort_order' => $field->sort_order,
            ])->values()),
        ];
    }
}
