<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Issues a batch of drafts, one by one.
 *
 * Deliberately not one big transaction: a draft that cannot be issued — no
 * items, a foreign currency with no rate — must not undo the twenty that were
 * fine. Each document stands or falls on its own, and the caller is told which
 * ones did not go.
 */
class IssueInvoices
{
    public function __construct(private readonly IssueInvoice $issue) {}

    /**
     * @param  iterable<int, Invoice>  $invoices
     * @return array{issued: Collection<int, Invoice>, failed: Collection<int, array{invoice: Invoice, reason: string}>}
     */
    public function handle(iterable $invoices): array
    {
        $issued = collect();
        $failed = collect();

        foreach ($invoices as $invoice) {
            try {
                $issued->push($this->issue->handle($invoice));
            } catch (ValidationException $exception) {
                $failed->push([
                    'invoice' => $invoice,
                    'reason' => collect($exception->errors())->flatten()->implode(' '),
                ]);
            }
        }

        return ['issued' => $issued, 'failed' => $failed];
    }
}
