<?php

namespace App\Models;

use App\Enums\PartnerType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'pib',
        'registration_number',
        'jmbg',
        'vat_id',
        'jbkjs',
        'address',
        'city',
        'postal_code',
        'country_code',
        'in_vat_system',
        'email',
        'phone',
        'contact_person',
        'payment_days',
        'default_currency',
        'notes',
        'is_active',
    ];

    protected $attributes = [
        'type' => PartnerType::LegalEntity->value,
        'country_code' => 'RS',
        'default_currency' => 'RSD',
        'payment_days' => 15,
        'in_vat_system' => false,
        'is_active' => true,
    ];

    protected $hidden = [
        'jmbg',
    ];

    protected function casts(): array
    {
        return [
            'type' => PartnerType::class,
            'in_vat_system' => 'boolean',
            'is_active' => 'boolean',
            'payment_days' => 'integer',
            // Personal data never sits in the database in the clear.
            'jmbg' => 'encrypted',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function ofType(Builder $query, PartnerType $type): Builder
    {
        return $query->where('type', $type);
    }

    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('pib', 'like', $like)
            ->orWhere('registration_number', 'like', $like)
            ->orWhere('city', 'like', $like));
    }

    /**
     * The identifier shown next to the name: PIB at home, VAT id abroad.
     */
    public function identifier(): ?string
    {
        return $this->type->isForeign()
            ? $this->vat_id
            : $this->pib;
    }

    /**
     * The audit trail must not become a second, plaintext copy of the JMBG.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return ['created_at', 'updated_at', 'jmbg'];
    }
}
