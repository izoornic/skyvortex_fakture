<?php

namespace App\Actions\Contracts;

use App\Actions\Invoices\SaveInvoiceItems;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Support\InvoiceTotals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns contracts into draft invoices.
 *
 * A run is tied to a date, not to a period, because contracts in the same
 * company may bill in opposite directions: on 1 April, one contract bills March
 * and the one next to it bills April. Each contract works out its own period.
 *
 * Nothing is ever issued here. The run produces drafts, the drafts are looked
 * at, and only then does somebody press the button — that is the whole point of
 * monthly invoicing being a review rather than a data entry job.
 */
class GenerateContractDrafts
{
    public function __construct(private readonly SaveInvoiceItems $items) {}

    /**
     * What a run on this date would produce, without producing it.
     *
     * @return Collection<int, array{contract: Contract, period: Carbon, supply_date: Carbon, due_date: Carbon, items: int, total: float, blocked: string|null}>
     */
    public function preview(Company $company, Carbon $on): Collection
    {
        return $this->due($company, $on)->map(function (Contract $contract) use ($on) {
            $period = $contract->periodFor($contract->generationDateIn($on));
            $items = $contract->items->map(fn ($item) => $item->toInvoiceItem())->all();
            $totals = InvoiceTotals::forDocument($items);

            return [
                'contract' => $contract,
                'period' => $period,
                'supply_date' => $contract->billing_mode->supplyDateFor($period),
                'due_date' => $on->copy()->addDays($contract->payment_days),
                'items' => count($items),
                'total' => (float) $totals['total'],
                'blocked' => $items === [] ? 'Ugovor nema stavki.' : null,
            ];
        })->values();
    }

    /**
     * @return array{created: Collection<int, Invoice>, skipped: Collection<int, array{contract: Contract, reason: string}>}
     */
    public function handle(Company $company, Carbon $on): array
    {
        $created = collect();
        $skipped = collect();

        foreach ($this->due($company, $on) as $contract) {
            if ($contract->items->isEmpty()) {
                $skipped->push(['contract' => $contract, 'reason' => 'Ugovor nema stavki.']);

                continue;
            }

            $created->push($this->draftFor($contract, $on));
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * One contract, one draft — used by the run and by the contract screen's
     * "generate now" button.
     */
    public function draftFor(Contract $contract, Carbon $on): Invoice
    {
        $company = $contract->company;
        $period = $contract->periodFor($contract->generationDateIn($on));

        return DB::transaction(function () use ($company, $contract, $on, $period) {
            $invoice = $company->invoices()->create([
                'partner_id' => $contract->partner_id,
                'contract_id' => $contract->getKey(),
                'type' => DocumentType::Invoice,
                'status' => InvoiceStatus::Draft,
                'period_year' => $period->year,
                'period_month' => $period->month,
                'issue_date' => $on->toDateString(),
                'supply_date' => $contract->billing_mode->supplyDateFor($period)->toDateString(),
                'due_date' => $on->copy()->addDays($contract->payment_days)->toDateString(),
                'currency' => $contract->currency,
                'exchange_rate' => $this->exchangeRateFor($company, $contract->currency),
                'bank_account_id' => $contract->bank_account_id
                    ?? $company->primaryBankAccount?->id
                    ?? $company->bankAccounts()->first()?->id,
                'vat_exemption_reason_id' => $company->vat_exemption_reason_id,
                'place_of_issue' => (string) $company->city,
                'note' => $contract->note,
                'valid_without_signature' => (bool) $contract->valid_without_signature,
                'internal_note' => $contract->internal_note,
                'created_by' => auth()->id(),
            ]);

            $this->items->handle($invoice, $contract->items->map(fn ($item) => $item->toInvoiceItem())->all());

            $contract->forceFill([
                'last_generated_year' => $period->year,
                'last_generated_month' => $period->month,
                'last_generated_at' => now(),
            ])->save();

            return $invoice->refresh();
        });
    }

    /**
     * Contracts whose draft for this run is owed and not yet made.
     *
     * Validity is checked against the period being billed, not against the day
     * of the run: a contract that ended on 31 March still owes its March
     * invoice, and that invoice is made in April.
     *
     * @return Collection<int, Contract>
     */
    public function due(Company $company, Carbon $on): Collection
    {
        $on = $on->copy()->startOfDay();

        // The company is named explicitly, so the run works the same from a
        // screen and from the console, where there is no active company.
        return Contract::acrossCompanies()
            ->where('company_id', $company->getKey())
            ->active()
            ->with(['partner', 'items'])
            ->orderBy('name')
            ->get()
            ->filter(function (Contract $contract) use ($on) {
                if ($contract->generationDateIn($on)->gt($on)) {
                    return false;
                }

                $period = $contract->periodFor($contract->generationDateIn($on));

                if ($contract->hasGenerated($period)) {
                    return false;
                }

                return $contract->coversPeriod($period);
            })
            ->values();
    }

    /**
     * The rate last used for this currency, so a foreign-currency draft does not
     * quietly arrive with a rate of 1. It is still entered by hand before the
     * document is issued — this only spares the retyping.
     */
    private function exchangeRateFor(Company $company, string $currency): float
    {
        if ($currency === 'RSD') {
            return 1.0;
        }

        $last = Invoice::acrossCompanies()
            ->where('company_id', $company->getKey())
            ->where('currency', $currency)
            ->whereNotNull('issued_at')
            ->latest('issue_date')
            ->value('exchange_rate');

        return $last ? (float) $last : 1.0;
    }
}
