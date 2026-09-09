<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

/**
 * Contracts are day-to-day invoicing work, not configuration: both roles keep
 * them, each inside the companies they may reach.
 */
class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->canAccessCompany($contract->company);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->canAccessCompany($contract->company);
    }

    /**
     * A contract that has already produced documents is not deleted — the
     * documents would lose what explains them. It is closed instead, by setting
     * an end date or switching it off.
     */
    public function delete(User $user, Contract $contract): bool
    {
        return $user->canAccessCompany($contract->company)
            && $contract->invoices()->count() === 0;
    }

    /**
     * Making the draft this contract owes, ahead of the scheduled run.
     */
    public function generate(User $user, Contract $contract): bool
    {
        return $contract->is_active && $user->canAccessCompany($contract->company);
    }
}
