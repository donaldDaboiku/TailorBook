<?php

namespace App\Http\Requests\Customer;

use App\Enums\Gender;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && ($this->user()?->can('update', $customer) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->user()?->business?->id;
        $customer = $this->route('customer');
        $customerId = $customer instanceof Customer ? $customer->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'max:32',
                Rule::unique('customers', 'phone')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at'))
                    ->ignore($customerId),
            ],
            'whatsapp_phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the customer name.',
            'phone.required' => 'Enter the customer phone number.',
            'phone.unique' => 'That phone number is already saved for a customer.',
        ];
    }
}
