<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'pib',
        'registration_number',
        'address',
        'city',
        'postal_code',
        'country_code',
        'activity_code',
        'jbkjs',
        'in_vat_system',
        'vat_registered_at',
        'default_currency',
        'email',
        'phone',
        'website',
        'logo_path',
        'is_active',
    ];

    protected $attributes = [
        'country_code' => 'RS',
        'default_currency' => 'RSD',
        'in_vat_system' => true,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'in_vat_system' => 'boolean',
            'is_active' => 'boolean',
            'vat_registered_at' => 'date',
        ];
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class)->orderBy('sort_order')->orderBy('id');
    }

    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class)->where('is_primary', true);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function numberSequences(): HasMany
    {
        return $this->hasMany(InvoiceNumberSequence::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Companies the given user may work with. Administrators reach all of them.
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): Builder
    {
        if ($user->role->seesAllCompanies()) {
            return $query;
        }

        return $query->whereHas('users', fn (Builder $q) => $q->whereKey($user->getKey()));
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }
}
