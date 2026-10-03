<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->business()->exists();
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->business?->id === $expense->business_id;
    }

    public function create(User $user): bool
    {
        return $user->business()->exists();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->business?->id === $expense->business_id;
    }
}
