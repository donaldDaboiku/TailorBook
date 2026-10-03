<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerJob;
use App\Models\User;

class CustomerJobPolicy
{
    public function viewAny(User $user, Customer $customer): bool
    {
        return $user->business?->id === $customer->business_id;
    }

    public function view(User $user, CustomerJob $job): bool
    {
        return $user->business?->id === $job->business_id;
    }

    public function create(User $user, Customer $customer): bool
    {
        return $user->business?->id === $customer->business_id;
    }

    public function delete(User $user, CustomerJob $job): bool
    {
        return $user->business?->id === $job->business_id;
    }
}
