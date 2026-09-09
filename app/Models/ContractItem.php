<?php

namespace App\Models;

use App\Enums\VatCategory;
use Database\Factories\ContractItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractItem extends Model
{
    /** @use HasFactory<ContractItemFactory> */
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'sort_order',
        'name',
        'description',
        'unit_code',
        'unit_symbol',
        'quantity',
        'unit_price',
        'discount_percent',
        'vat_rate',
        'vat_category',
        'vat_exemption_reason_id',
    ];

    protected $attributes = [
        'unit_code' => 'H87',
        'quantity' => 1,
        'unit_price' => 0,
        'discount_percent' => 0,
        'vat_rate' => 0,
        'vat_category' => 'S',
    ];

    protected function casts(): array
    {
        return [
            'vat_category' => VatCategory::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function vatExemptionReason(): BelongsTo
    {
        return $this->belongsTo(VatExemptionReason::class);
    }

    /**
     * The shape `SaveInvoiceItems` expects, so a contract line becomes a
     * document line without anything in between reshaping it.
     *
     * @return array<string, mixed>
     */
    public function toInvoiceItem(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'unit_code' => $this->unit_code,
            'unit_symbol' => $this->unit_symbol,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount_percent' => (float) $this->discount_percent,
            'vat_rate' => (float) $this->vat_rate,
            'vat_category' => $this->vat_category->value,
            'vat_exemption_reason_id' => $this->vat_exemption_reason_id,
        ];
    }
}
