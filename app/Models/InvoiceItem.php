<?php

namespace App\Models;

use App\Enums\VatCategory;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
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
        'line_subtotal',
        'line_discount',
        'line_vat',
        'line_total',
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
            'line_subtotal' => 'decimal:2',
            'line_discount' => 'decimal:2',
            'line_vat' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vatExemptionReason(): BelongsTo
    {
        return $this->belongsTo(VatExemptionReason::class);
    }
}
