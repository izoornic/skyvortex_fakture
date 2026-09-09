<?php

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\ContractFrequency;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\PeriodLabel;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A standing agreement that turns into a draft invoice every month.
 *
 * It holds what an invoice would hold — partner, currency, payment terms, items
 * — but never a number and never amounts: those belong to the documents it
 * makes, each computed when it is made.
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'partner_id',
        'name',
        'reference',
        'frequency',
        'billing_mode',
        'generation_day',
        'starts_on',
        'ends_on',
        'currency',
        'payment_days',
        'bank_account_id',
        'note',
        'valid_without_signature',
        'internal_note',
        'is_active',
        'last_generated_year',
        'last_generated_month',
        'last_generated_at',
        'created_by',
    ];

    protected $attributes = [
        'frequency' => ContractFrequency::Monthly->value,
        'billing_mode' => BillingMode::Arrears->value,
        'generation_day' => 1,
        'currency' => 'RSD',
        'payment_days' => 15,
        'is_active' => true,
        'valid_without_signature' => true,
    ];

    protected function casts(): array
    {
        return [
            'frequency' => ContractFrequency::class,
            'billing_mode' => BillingMode::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'last_generated_at' => 'datetime',
            'is_active' => 'boolean',
            'valid_without_signature' => 'boolean',
            'generation_day' => 'integer',
            'payment_days' => 'integer',
            'last_generated_year' => 'integer',
            'last_generated_month' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether the contract was in force during the month being billed.
     *
     * Checked against the period, never against the day the draft is made: a
     * contract that ended on 31 March still owes its March invoice, and that
     * invoice is made in April, after the contract is over.
     */
    public function coversPeriod(Carbon $period): bool
    {
        if ($period->copy()->endOfMonth()->lt($this->starts_on)) {
            return false;
        }

        return $this->ends_on === null
            || $period->copy()->startOfMonth()->lte($this->ends_on);
    }

    /**
     * The month this contract bills when a draft is made on the given day.
     */
    public function periodFor(Carbon $generatedOn): Carbon
    {
        return $this->billing_mode->periodFor($generatedOn);
    }

    /**
     * The day of the month the draft is due to appear. A contract set to the
     * 31st still generates in February, on the 28th.
     */
    public function generationDateIn(Carbon $month): Carbon
    {
        $startOfMonth = $month->copy()->startOfMonth();

        return $startOfMonth->copy()->setDay(
            min($this->generation_day, $startOfMonth->daysInMonth)
        );
    }

    /**
     * Has the given period already been turned into a document?
     */
    public function hasGenerated(Carbon $period): bool
    {
        return $this->last_generated_year === $period->year
            && $this->last_generated_month === $period->month;
    }

    /**
     * The period this contract will bill next, and the day it will happen —
     * what the contract screen promises the user.
     *
     * @return array{period: Carbon, on: Carbon}|null
     */
    public function nextRun(?Carbon $from = null): ?array
    {
        $from = ($from ?? Carbon::now())->copy()->startOfDay();

        if (! $this->is_active) {
            return null;
        }

        for ($month = $from->copy()->startOfMonth(), $tries = 0; $tries < 24; $month->addMonth(), $tries++) {
            $on = $this->generationDateIn($month);

            if ($on->lt($from)) {
                continue;
            }

            $period = $this->periodFor($on);

            if ($this->hasGenerated($period) || ! $this->coversPeriod($period)) {
                continue;
            }

            return ['period' => $period, 'on' => $on];
        }

        return null;
    }

    public static function periodLabel(Carbon $period): string
    {
        return PeriodLabel::forDate($period);
    }
}
