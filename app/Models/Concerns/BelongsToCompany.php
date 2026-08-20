<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every model owned by a company. Adds the tenancy scope and fills
 * company_id from the active company, so no screen has to remember to do it.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (! $model->company_id) {
                $model->company_id = app(CurrentCompany::class)->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Escape hatch for cross-company work — reports, console commands, tests.
     * Use it deliberately; it removes the tenancy boundary.
     */
    public static function acrossCompanies(): Builder
    {
        return static::query()->withoutGlobalScope(CompanyScope::class);
    }
}
