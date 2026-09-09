<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\PartnerGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Partners paid from one place — several housing communities under the same
 * manager, several companies of the same owner. The group receives one PDF with
 * every invoice of its partners for a period, at one address.
 *
 * The group is a delivery channel, never a billing unit: invoices keep being
 * made out to the individual partner, with their own number and reference.
 */
class PartnerGroup extends Model
{
    /** @use HasFactory<PartnerGroupFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'contact_person',
        'phone',
        'notes',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    /**
     * Every invoice of every partner in the group, across all periods.
     */
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, Partner::class, 'partner_group_id', 'partner_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('contact_person', 'like', $like));
    }

    /**
     * A group without an address can still produce the PDF, but nothing can be
     * sent to it.
     */
    public function canReceiveMail(): bool
    {
        return filled($this->email);
    }
}
