<?php

namespace App\Http\Requests\Expense;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'spent_on' => ['required', 'date'],
            'expense_category_id' => [
                'required',
                'uuid',
                Rule::exists('expense_categories', 'id')->whereNull('business_id'),
            ],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Enter the expense amount.',
            'amount.gt' => 'The expense amount must be greater than zero.',
            'expense_category_id.required' => 'Choose a category.',
            'expense_category_id.exists' => 'That category was not found.',
            'method.required' => 'Choose how it was paid.',
        ];
    }
}
