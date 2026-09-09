<?php

namespace App\Actions\Invoices;

use App\Enums\VatCategory;
use App\Models\Invoice;
use App\Support\InvoiceTotals;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a draft's items and recomputes every amount on the document, so the
 * stored totals can never drift away from the lines they come from.
 */
class SaveInvoiceItems
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(Invoice $invoice, array $items): Invoice
    {
        return DB::transaction(function () use ($invoice, $items) {
            $invoice->items()->delete();

            foreach (array_values($items) as $position => $item) {
                $amounts = InvoiceTotals::forLine($item);

                $invoice->items()->create([
                    'sort_order' => $position,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'unit_code' => $item['unit_code'] ?? 'H87',
                    'unit_symbol' => $item['unit_symbol'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'vat_rate' => $item['vat_rate'] ?? 0,
                    'vat_category' => $item['vat_category'] ?? 'S',
                    'vat_exemption_reason_id' => $this->exemptionReasonFor($invoice, $item),
                    ...$amounts,
                ]);
            }

            $totals = InvoiceTotals::forDocument($items);

            $invoice->forceFill([
                ...$totals,
                ...InvoiceTotals::inDinars(
                    $totals['subtotal'],
                    $totals['vat_total'],
                    $totals['total'],
                    (float) $invoice->exchange_rate,
                ),
            ])->save();

            return $invoice->refresh();
        });
    }

    /**
     * A line that charges no VAT has to say under which article, and EN 16931
     * asks for it per VAT category, not once per document. Nobody types it on
     * every line, so a line without one inherits the document's basis — which
     * itself comes from the company.
     *
     * @param  array<string, mixed>  $item
     */
    private function exemptionReasonFor(Invoice $invoice, array $item): ?int
    {
        if (($item['vat_exemption_reason_id'] ?? null) !== null) {
            return (int) $item['vat_exemption_reason_id'];
        }

        $category = VatCategory::tryFrom((string) ($item['vat_category'] ?? VatCategory::Standard->value));

        return $category?->requiresExemptionReason()
            ? $invoice->vat_exemption_reason_id
            : null;
    }
}
