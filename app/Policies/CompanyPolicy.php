<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Both roles get a list — it is filtered to what they may reach.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->canAccessCompany($company);
    }

    /**
     * Companies are set up and assigned by administrators only.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }

    /**
     * Switching the active company is allowed for anything the user may reach.
     */
    public function select(User $user, Company $company): bool
    {
        return $user->canAccessCompany($company);
    }
}
