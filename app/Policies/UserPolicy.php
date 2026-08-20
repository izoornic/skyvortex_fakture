<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Managing users and their company assignments is administrator work.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * An administrator must not delete their own account and lock everyone out.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    public function assignCompanies(User $user, User $model): bool
    {
        return $user->isAdmin();
    }
}
