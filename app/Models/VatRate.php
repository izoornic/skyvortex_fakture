<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VatRate extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'rate',
        'valid_from',
        'valid_to',
        'is_default',
        'sort_order',
    ];

    protected $attributes = [
        'is_default' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Rates in force on a given day. An invoice must use the rate that applied
     * on its supply date, not the one in force today.
     */
    #[Scope]
    protected function validOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $q) => $q
                ->whereNull('valid_to')
                ->orWhereDate('valid_to', '>=', $date));
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('rate');
    }

    public function displayName(): string
    {
        return "{$this->name} ({$this->rate}%)";
    }
}
