<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class InvoiceNumberSequence extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'type',
        'year',
        'last_number',
    ];

    protected $attributes = [
        'last_number' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }

    /**
     * The number this sequence would hand out next, e.g. 2026-0001.
     * Handing it out transactionally belongs to M2.
     */
    public function previewNextNumber(): string
    {
        return str_replace(
            ['{godina}', '{broj}'],
            [$this->year, str_pad((string) ($this->last_number + 1), 4, '0', STR_PAD_LEFT)],
            $this->type->numberTemplate(),
        );
    }
}
