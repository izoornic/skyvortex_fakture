<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Copy last month" — a new draft with the same lines, one period on.
 *
 * For clients that recur without a contract behind them. The copy keeps what
 * describes the work and drops everything that belongs to the original
 * document: its number, its payment reference, its dates, its history.
 */
class CopyInvoiceToNextPeriod
{
    public function __construct(private readonly SaveInvoiceItems $items) {}

    public function handle(Invoice $invoice, ?Carbon $issueDate = null): Invoice
    {
        $issueDate = ($issueDate ?? Carbon::now())->copy()->startOfDay();

        $period = Carbon::create($invoice->period_year, $invoice->period_month, 1)->addMonth();

        // The same distance between supply date and the end of its month, so a
        // document supplied on the last day stays on the last day.
        $supplyDate = $invoice->supply_date->isLastOfMonth()
            ? $period->copy()->endOfMonth()->startOfDay()
            : $period->copy()->setDay(min($invoice->supply_date->day, $period->copy()->endOfMonth()->day));

        $paymentDays = $invoice->issue_date->diffInDays($invoice->due_date);

        return DB::transaction(function () use ($invoice, $issueDate, $period, $supplyDate, $paymentDays) {
            $copy = $invoice->company->invoices()->create([
                'partner_id' => $invoice->partner_id,
                'contract_id' => $invoice->contract_id,
                'source_invoice_id' => $invoice->getKey(),
                'type' => $invoice->type,
                'status' => InvoiceStatus::Draft,
                'period_year' => $period->year,
                'period_month' => $period->month,
                'issue_date' => $issueDate->toDateString(),
                'supply_date' => $supplyDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays((int) $paymentDays)->toDateString(),
                'currency' => $invoice->currency,
                'exchange_rate' => $invoice->exchange_rate,
                'bank_account_id' => $invoice->bank_account_id,
                'vat_exemption_reason_id' => $invoice->vat_exemption_reason_id,
                'place_of_issue' => $invoice->place_of_issue,
                'note' => $invoice->note,
                'valid_without_signature' => (bool) $invoice->valid_without_signature,
                'internal_note' => $invoice->internal_note,
                'created_by' => auth()->id(),
            ]);

            $this->items->handle($copy, $invoice->items->map(fn ($item) => [
                'name' => $item->name,
                'description' => $item->description,
                'unit_code' => $item->unit_code,
                'unit_symbol' => $item->unit_symbol,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_percent' => (float) $item->discount_percent,
                'vat_rate' => (float) $item->vat_rate,
                'vat_category' => $item->vat_category->value,
                'vat_exemption_reason_id' => $item->vat_exemption_reason_id,
            ])->all());

            return $copy->refresh();
        });
    }
}
