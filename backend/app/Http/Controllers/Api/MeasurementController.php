<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeasurementUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Measurement\StoreMeasurementRequest;
use App\Http\Resources\MeasurementResource;
use App\Models\Customer;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
use App\Services\Units;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeasurementController extends Controller
{
    public function index(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Measurement::class, $customer]);

        $unit = $this->displayUnit($request);

        $measurements = Measurement::query()
            ->where('customer_id', $customer->id)
            ->with(['template', 'values.field'])
            ->orderByDesc('taken_on')
            ->orderByDesc('created_at')
            ->get();

        return tap(
            MeasurementResource::collection($measurements),
            function (AnonymousResourceCollection $collection) use ($unit) {
                $collection->collection->each(function (MeasurementResource $resource) use ($unit) {
                    $resource->displayUnit = $unit;
                });
            },
        );
    }

    public function store(StoreMeasurementRequest $request, Customer $customer): JsonResponse
    {
        $business = $request->user()?->business;
        abort_unless($business !== null, 403, 'Set up your shop first.');

        $data = $request->validated();
        $unit = MeasurementUnit::from($data['unit']);

        $template = MeasurementTemplate::query()
            ->whereNull('business_id')
            ->where('slug', $data['template_slug'])
            ->with('fields')
            ->firstOrFail();

        $fieldsByKey = $template->fields->keyBy('key');
        $unknown = collect(array_keys($data['values']))->diff($fieldsByKey->keys());

        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'values' => ['Unknown measurement field: '.$unknown->first()],
            ]);
        }

        $measurement = DB::transaction(function () use ($business, $customer, $template, $data, $unit, $fieldsByKey) {
            $measurement = Measurement::query()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'measurement_template_id' => $template->id,
                'taken_on' => $data['taken_on'],
            ]);

            foreach ($data['values'] as $key => $value) {
                $field = $fieldsByKey->get($key);

                $measurement->values()->create([
                    'measurement_field_id' => $field->id,
                    'value_cm' => Units::toCm($value, $unit),
                ]);
            }

            return $measurement->load(['template', 'values.field']);
        });

        $resource = new MeasurementResource($measurement);
        $resource->displayUnit = $unit;
        $resource->previous = $this->previousMeasurement($customer, $measurement);

        return $resource->response()->setStatusCode(201);
    }

    public function show(Request $request, Customer $customer, Measurement $measurement): MeasurementResource
    {
        abort_unless($measurement->customer_id === $customer->id, 404);
        $this->authorize('view', $measurement);

        $measurement->load(['template', 'values.field']);

        $resource = new MeasurementResource($measurement);
        $resource->displayUnit = $this->displayUnit($request);
        $resource->previous = $this->previousMeasurement($customer, $measurement);

        return $resource;
    }

    private function displayUnit(Request $request): MeasurementUnit
    {
        $requested = $request->query('unit');

        if (is_string($requested) && in_array($requested, ['cm', 'in'], true)) {
            return MeasurementUnit::from($requested);
        }

        return $request->user()?->business?->measurement_unit ?? MeasurementUnit::Inches;
    }

    private function previousMeasurement(Customer $customer, Measurement $measurement): ?Measurement
    {
        return Measurement::query()
            ->where('customer_id', $customer->id)
            ->where('id', '!=', $measurement->id)
            ->where(function ($query) use ($measurement) {
                $query->where('taken_on', '<', $measurement->taken_on)
                    ->orWhere(function ($inner) use ($measurement) {
                        $inner->whereDate('taken_on', $measurement->taken_on)
                            ->where('created_at', '<', $measurement->created_at);
                    });
            })
            ->with('values.field')
            ->orderByDesc('taken_on')
            ->orderByDesc('created_at')
            ->first();
    }
}
