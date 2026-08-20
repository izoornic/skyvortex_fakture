<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->canAccessCompany($bankAccount->company);
    }

    /**
     * Bank accounts are part of company setup, which is administrator work.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->isAdmin() && $user->canAccessCompany($bankAccount->company);
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->update($user, $bankAccount);
    }
}
