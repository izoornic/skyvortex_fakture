<?php

namespace App\Policies;

use App\Models\PartnerGroup;
use App\Models\User;

/**
 * Groups are day-to-day bookkeeping work, like the partners in them: both roles
 * maintain them, each inside the companies they may reach.
 */
class PartnerGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PartnerGroup $partnerGroup): bool
    {
        return $user->canAccessCompany($partnerGroup->company);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PartnerGroup $partnerGroup): bool
    {
        return $user->canAccessCompany($partnerGroup->company);
    }

    /**
     * A group that still has members is not deleted — its partners would lose
     * where their invoices are delivered. It is emptied first, or switched off.
     */
    public function delete(User $user, PartnerGroup $partnerGroup): bool
    {
        return $user->canAccessCompany($partnerGroup->company)
            && $partnerGroup->partners()->count() === 0;
    }
}
