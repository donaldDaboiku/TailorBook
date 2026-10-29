<?php

namespace App\Http\Requests\Auth;

use App\Enums\MeasurementUnit;
use App\Support\NigerianStates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'business_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'state' => ['required', 'string', Rule::in(NigerianStates::all())],
            'measurement_unit' => ['required', Rule::enum(MeasurementUnit::class)],
            'currency' => ['required', 'string', 'size:3'],
            'invite_code' => ['nullable', 'string', 'max:12'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('invite_code')) {
            return;
        }

        $code = strtoupper(trim((string) $this->input('invite_code')));
        $this->merge([
            'invite_code' => $code === '' ? null : $code,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'That email is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
            'business_name.required' => 'Enter your shop or fashion house name.',
            'phone.required' => 'Enter your shop phone number.',
            'state.required' => 'Choose the state your shop is in.',
            'state.in' => 'Choose a Nigerian state.',
            'invite_code.max' => 'That invite code is not recognised.',
        ];
    }
}
