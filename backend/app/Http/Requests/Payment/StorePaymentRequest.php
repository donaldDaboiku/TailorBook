<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && ($this->user()?->can('create', [Payment::class, $customer]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $customerId = $customer instanceof Customer ? $customer->id : null;

        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'customer_job_id' => [
                'nullable',
                'uuid',
                Rule::exists('customer_jobs', 'id')
                    ->where(fn ($query) => $query->where('customer_id', $customerId)->whereNull('deleted_at')),
            ],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Enter the amount paid.',
            'amount.gt' => 'The payment amount must be greater than zero.',
            'method.required' => 'Choose how they paid.',
            'customer_job_id.exists' => 'That job was not found for this customer.',
        ];
    }
}
