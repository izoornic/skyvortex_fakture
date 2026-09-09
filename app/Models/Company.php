<?php

namespace App\Models;

use App\Enums\CompanyType;
use App\Models\Concerns\Auditable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory;

    /**
     * Logos are private files: they are served by a controller that checks who
     * is asking, never straight from the public directory.
     */
    public const LOGO_DISK = 'local';

    public const LOGO_DIRECTORY = 'logos';

    /**
     * Bezgotovinski promet robe i usluga — the usual code on an invoice between
     * companies. Printed into the NBS IPS QR code.
     */
    public const DEFAULT_PAYMENT_CODE = '221';

    protected $fillable = [
        'name',
        'short_name',
        'type',
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
        'vat_exemption_reason_id',
        'default_currency',
        'payment_code',
        'email',
        'phone',
        'website',
        'logo_path',
        'is_active',
    ];

    protected $attributes = [
        'type' => CompanyType::LegalEntity->value,
        'country_code' => 'RS',
        'default_currency' => 'RSD',
        'payment_code' => self::DEFAULT_PAYMENT_CODE,
        'in_vat_system' => true,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
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

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function partnerGroups(): HasMany
    {
        return $this->hasMany(PartnerGroup::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * The legal basis every document of this company falls back to when no VAT
     * is charged on a line.
     */
    public function vatExemptionReason(): BelongsTo
    {
        return $this->belongsTo(VatExemptionReason::class);
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

    public function hasLogo(): bool
    {
        return $this->logo_path !== null
            && Storage::disk(self::LOGO_DISK)->exists($this->logo_path);
    }

    public function logoMimeType(): ?string
    {
        return match (strtolower(pathinfo((string) $this->logo_path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => null,
        };
    }

    public function logoContents(): ?string
    {
        if (! $this->hasLogo()) {
            return null;
        }

        return Storage::disk(self::LOGO_DISK)->get($this->logo_path);
    }

    /**
     * dompdf never reaches out over the network, so the logo travels into the
     * document inline.
     */
    public function logoDataUri(): ?string
    {
        $contents = $this->logoContents();

        if ($contents === null || $this->logoMimeType() === null) {
            return null;
        }

        return 'data:'.$this->logoMimeType().';base64,'.base64_encode($contents);
    }

    /**
     * The file name changes with every upload, so the URL carries a fingerprint
     * of it and a replaced logo is never served from cache.
     */
    public function logoUrl(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        return route('companies.logo', [
            'company' => $this,
            'v' => substr(md5($this->logo_path), 0, 8),
        ]);
    }
}
