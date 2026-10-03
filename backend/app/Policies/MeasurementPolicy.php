<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Measurement;
use App\Models\User;

class MeasurementPolicy
{
    public function viewAny(User $user, Customer $customer): bool
    {
        return $user->business?->id === $customer->business_id;
    }

    public function view(User $user, Measurement $measurement): bool
    {
        return $user->business?->id === $measurement->business_id;
    }

    public function create(User $user, Customer $customer): bool
    {
        return $user->business?->id === $customer->business_id;
    }
}
