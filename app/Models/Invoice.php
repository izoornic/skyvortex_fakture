<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'partner_id',
        'type',
        'status',
        'number',
        'number_year',
        'number_sequence',
        'period_year',
        'period_month',
        'issue_date',
        'supply_date',
        'due_date',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount_total',
        'vat_total',
        'total',
        'subtotal_rsd',
        'vat_total_rsd',
        'total_rsd',
        'payment_reference',
        'payment_reference_model',
        'bank_account_id',
        'vat_exemption_reason_id',
        'place_of_issue',
        'note',
        'internal_note',
        'contract_id',
        'source_invoice_id',
        'created_by',
        'issued_at',
        'sent_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $attributes = [
        'type' => DocumentType::Invoice->value,
        'status' => InvoiceStatus::Draft->value,
        'currency' => 'RSD',
        'exchange_rate' => 1,
        'payment_reference_model' => '97',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'supply_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'subtotal_rsd' => 'decimal:2',
            'vat_total_rsd' => 'decimal:2',
            'total_rsd' => 'decimal:2',
            'period_year' => 'integer',
            'period_month' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function vatExemptionReason(): BelongsTo
    {
        return $this->belongsTo(VatExemptionReason::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_invoice_id');
    }

    #[Scope]
    protected function inPeriod(Builder $query, int $year, ?int $month = null): Builder
    {
        return $query
            ->where('period_year', $year)
            ->when($month, fn (Builder $q) => $q->where('period_month', $month));
    }

    #[Scope]
    protected function issued(Builder $query): Builder
    {
        return $query->whereNotNull('issued_at');
    }

    #[Scope]
    protected function drafts(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Draft);
    }

    /**
     * Documents that count towards revenue: issued and not cancelled.
     */
    #[Scope]
    protected function countable(Builder $query): Builder
    {
        return $query->issued()->where('status', '!=', InvoiceStatus::Cancelled);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isForeignCurrency(): bool
    {
        return $this->currency !== 'RSD';
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'nacrt';
    }

    public function fullPaymentReference(): ?string
    {
        return $this->payment_reference
            ? "{$this->payment_reference_model} {$this->payment_reference}"
            : null;
    }

    public function periodLabel(): string
    {
        $months = [
            1 => 'januar', 'februar', 'mart', 'april', 'maj', 'jun',
            'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar',
        ];

        return ($months[$this->period_month] ?? '').' '.$this->period_year.'.';
    }

    /**
     * Internal notes are for the bookkeeper, not for the audit trail's diff.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return ['created_at', 'updated_at'];
    }
}
