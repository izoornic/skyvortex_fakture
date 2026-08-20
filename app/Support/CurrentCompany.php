<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Holds the company the user is currently working in.
 *
 * Resolution always goes through the authenticated user's accessible companies,
 * so a stale or forged session value can never widen access.
 */
class CurrentCompany
{
    private const SESSION_KEY = 'current_company_id';

    private ?Company $resolved = null;

    private bool $hasResolved = false;

    public function __construct(private readonly Session $session) {}

    public function get(): ?Company
    {
        if ($this->hasResolved) {
            return $this->resolved;
        }

        $this->hasResolved = true;

        $user = Auth::user();

        if (! $user) {
            return $this->resolved = null;
        }

        $id = $this->session->get(self::SESSION_KEY);

        $company = $id
            ? $user->accessibleCompanies()->whereKey($id)->first()
            : null;

        // No valid selection: fall back to the first company the user may see,
        // so every screen has a company to work with right after login.
        $company ??= $user->accessibleCompanies()->orderBy('name')->first();

        if ($company) {
            $this->session->put(self::SESSION_KEY, $company->id);
        } else {
            $this->session->forget(self::SESSION_KEY);
        }

        return $this->resolved = $company;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /**
     * Switch the active company. Returns false when the user may not access it.
     */
    public function set(Company $company): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->canAccessCompany($company)) {
            return false;
        }

        $this->session->put(self::SESSION_KEY, $company->id);
        $this->resolved = $company;
        $this->hasResolved = true;

        return true;
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->resolved = null;
        $this->hasResolved = false;
    }

    /**
     * Drop the memoised value so the next call re-resolves it.
     */
    public function refresh(): void
    {
        $this->resolved = null;
        $this->hasResolved = false;
    }
}
