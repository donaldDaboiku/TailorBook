<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Measurement\UpdateMeasurementFieldsRequest;
use App\Models\Business;
use App\Models\BusinessMeasurementFieldPref;
use App\Models\MeasurementTemplate;
use App\Services\MeasurementTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $onlyEnabled = $request->boolean('enabled_only');

        return response()->json([
            'data' => MeasurementTemplates::forBusiness($business, $onlyEnabled),
        ]);
    }

    public function updateFields(
        UpdateMeasurementFieldsRequest $request,
        string $slug,
    ): JsonResponse {
        $business = $this->business($request);

        $template = MeasurementTemplate::query()
            ->whereNull('business_id')
            ->where('slug', $slug)
            ->with('fields')
            ->firstOrFail();

        $fieldsByKey = $template->fields->keyBy('key');
        $payload = collect($request->validated('fields'));

        foreach ($payload as $row) {
            abort_unless($fieldsByKey->has($row['key']), 422, "Unknown field: {$row['key']}");
        }

        foreach ($payload as $row) {
            $field = $fieldsByKey->get($row['key']);
            $label = trim($row['label']);
            $defaultLabel = $field->label;
            $enabled = (bool) $row['enabled'];

            $isDefault = $enabled && $label === $defaultLabel;

            if ($isDefault) {
                BusinessMeasurementFieldPref::query()
                    ->where('business_id', $business->id)
                    ->where('measurement_field_id', $field->id)
                    ->delete();

                continue;
            }

            BusinessMeasurementFieldPref::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'measurement_field_id' => $field->id,
                ],
                [
                    'label' => $label === $defaultLabel ? null : $label,
                    'enabled' => $enabled,
                ],
            );
        }

        return response()->json([
            'data' => MeasurementTemplates::forBusiness($business)
                ->firstWhere('slug', $slug),
        ]);
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
