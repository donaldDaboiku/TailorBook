<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessMeasurementFieldPref;
use App\Models\MeasurementTemplate;
use Illuminate\Support\Collection;

class MeasurementTemplates
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function forBusiness(Business $business, bool $onlyEnabled = false): Collection
    {
        $prefs = BusinessMeasurementFieldPref::query()
            ->where('business_id', $business->id)
            ->get()
            ->keyBy('measurement_field_id');

        return MeasurementTemplate::query()
            ->whereNull('business_id')
            ->with('fields')
            ->orderBy('sort_order')
            ->get()
            ->map(function (MeasurementTemplate $template) use ($prefs, $onlyEnabled) {
                $fields = $template->fields->map(function ($field) use ($prefs) {
                    $pref = $prefs->get($field->id);
                    $enabled = $pref?->enabled ?? true;
                    $label = filled($pref?->label) ? (string) $pref->label : $field->label;

                    return [
                        'id' => $field->id,
                        'key' => $field->key,
                        'label' => $label,
                        'default_label' => $field->label,
                        'enabled' => $enabled,
                        'sort_order' => $field->sort_order,
                    ];
                });

                if ($onlyEnabled) {
                    $fields = $fields->where('enabled', true)->values();
                }

                return [
                    'id' => $template->id,
                    'slug' => $template->slug,
                    'name' => $template->name,
                    'fields' => $fields->values(),
                ];
            })
            ->values();
    }
}
