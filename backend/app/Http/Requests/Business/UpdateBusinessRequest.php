<?php

namespace App\Http\Requests\Business;

use App\Enums\MeasurementUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'whatsapp_phone' => ['nullable', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'measurement_unit' => ['required', Rule::enum(MeasurementUnit::class)],
            'currency' => ['required', 'string', 'size:3'],
            'receipt_header' => ['nullable', 'string', 'max:240'],
            'receipt_footer' => ['nullable', 'string', 'max:240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter your shop or fashion house name.',
            'phone.required' => 'Enter your shop phone number.',
        ];
    }
}
