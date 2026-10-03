<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->business()->exists();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer);
    }

    public function create(User $user): bool
    {
        return $user->business()->exists();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer);
    }

    private function owns(User $user, Customer $customer): bool
    {
        return $user->business?->id === $customer->business_id;
    }
}
