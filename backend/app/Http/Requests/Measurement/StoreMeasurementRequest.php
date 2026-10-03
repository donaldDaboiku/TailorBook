<?php

namespace App\Http\Requests\Measurement;

use App\Enums\MeasurementUnit;
use App\Models\Customer;
use App\Models\Measurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && ($this->user()?->can('create', [Measurement::class, $customer]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_slug' => ['required', 'string', Rule::exists('measurement_templates', 'slug')->whereNull('business_id')],
            'taken_on' => ['required', 'date'],
            'unit' => ['required', Rule::enum(MeasurementUnit::class)],
            'values' => ['required', 'array', 'min:1'],
            'values.*' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_slug.required' => 'Choose a measurement template.',
            'values.required' => 'Enter at least one measurement.',
            'values.*.gt' => 'Each measurement must be greater than zero.',
        ];
    }
}
