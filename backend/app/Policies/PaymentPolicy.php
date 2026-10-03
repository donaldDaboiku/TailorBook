<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user, Customer $customer): bool
    {
        return $user->business?->id === $customer->business_id;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->business?->id === $payment->business_id;
    }

    public function create(User $user, Customer $customer): bool
    {
        return $user->business?->id === $customer->business_id;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->business?->id === $payment->business_id;
    }
}
