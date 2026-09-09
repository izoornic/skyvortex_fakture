<?php

namespace App\Actions\PartnerGroups;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PartnerGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What goes into a group's consolidated bundle, and what stays out of it.
 *
 * The rule is deliberately independent of any screen filter: the bundle always
 * carries the issued invoices of the group's partners for the chosen period. A
 * search term typed into a list must never quietly drop an invoice from a
 * shipment the customer is expecting.
 */
class CollectGroupInvoices
{
    /**
     * Issued documents of the group, oldest partner first, in the order they
     * will be printed.
     *
     * @return Collection<int, Invoice>
     */
    public function handle(PartnerGroup $group, int $year, ?int $month = null): Collection
    {
        return $this->inPeriod($group, $year, $month)
            ->countable()
            ->with([
                'partner',
                'company.primaryBankAccount',
                'bankAccount',
                'items.vatExemptionReason',
                'vatExemptionReason',
            ])
            ->get()
            ->sortBy(fn (Invoice $invoice) => $invoice->partner->name.'|'.$invoice->displayNumber())
            ->values();
    }

    /**
     * Documents of the group that exist in the period but stay out of the
     * bundle, each with the reason — the screen has to be able to say why an
     * invoice the user can see is not in the document.
     *
     * @return Collection<int, array{invoice: Invoice, reason: string}>
     */
    public function excluded(PartnerGroup $group, int $year, ?int $month = null): Collection
    {
        return $this->inPeriod($group, $year, $month)
            ->whereIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->with('partner')
            ->orderBy('id')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'invoice' => $invoice,
                'reason' => $invoice->status === InvoiceStatus::Draft
                    ? 'Nacrt još nije dokument.'
                    : 'Storno je već poslat sam za sebe.',
            ]);
    }

    /**
     * @return Builder<Invoice>
     */
    private function inPeriod(PartnerGroup $group, int $year, ?int $month): Builder
    {
        return Invoice::query()
            ->where('company_id', $group->company_id)
            ->inPeriod($year, $month)
            ->whereHas('partner', fn (Builder $query) => $query->where('partner_group_id', $group->getKey()));
    }
}
