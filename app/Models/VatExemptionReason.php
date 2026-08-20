<?php

namespace App\Models;

use App\Enums\VatCategory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VatExemptionReason extends Model
{
    use Auditable;

    protected $fillable = [
        'code',
        'vat_category',
        'description',
        'legal_basis',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'vat_category' => VatCategory::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function forCategory(Builder $query, VatCategory $category): Builder
    {
        return $query->where('vat_category', $category);
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }
}
