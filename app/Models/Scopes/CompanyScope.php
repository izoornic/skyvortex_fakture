<?php

namespace App\Models\Scopes;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenancy scope: keeps company-owned records inside the company the user
 * is currently working in, and never outside the companies they may access.
 *
 * Without an authenticated user the scope stays out of the way, so console
 * commands, queued jobs and seeders see the whole table.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $column = $model->qualifyColumn('company_id');

        if ($companyId = app(CurrentCompany::class)->id()) {
            $builder->where($column, $companyId);

            return;
        }

        // No active company (admin who has not picked one, or a bookkeeper with
        // no assignments): fall back to the access boundary itself.
        if (! $user->role->seesAllCompanies()) {
            $builder->whereIn($column, $user->accessibleCompanyIds());
        }
    }
}
