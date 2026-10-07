<?php

namespace App\Http\Requests\Measurement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeasurementFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->business !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.required' => 'Send the measurement fields to save.',
            'fields.*.label.required' => 'Each field needs a name.',
        ];
    }
}
