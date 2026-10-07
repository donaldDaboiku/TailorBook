<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'subscribed_until' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $this->input('subscription_status') === SubscriptionStatus::Subscribed->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subscribed_until.required' => 'Set a subscribed-until date for paid shops.',
        ];
    }
}
