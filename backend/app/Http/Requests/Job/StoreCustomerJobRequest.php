<?php

namespace App\Http\Requests\Job;

use App\Models\Customer;
use App\Models\CustomerJob;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && ($this->user()?->can('create', [CustomerJob::class, $customer]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'agreed_amount' => ['required', 'numeric', 'gt:0'],
            'service_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Enter what the job is for.',
            'agreed_amount.required' => 'Enter how much the customer should pay.',
            'agreed_amount.gt' => 'The agreed amount must be greater than zero.',
        ];
    }
}
