<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

/**
 * Partners are day-to-day bookkeeping work, so both roles maintain them —
 * within the companies they may reach.
 */
class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->canAccessCompany($partner->company);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->canAccessCompany($partner->company);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->canAccessCompany($partner->company);
    }

    public function import(User $user): bool
    {
        return true;
    }
}
