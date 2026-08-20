<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Storno. Every role may do it without approval, so the who and the why are
 * recorded on the document itself and in the audit trail.
 *
 * The number is deliberately kept: a cancelled document still occupies its
 * place in the sequence, and a gap would be harder to explain than a storno.
 */
class CancelInvoice
{
    public function handle(Invoice $invoice, User $user, string $reason): Invoice
    {
        if (! $invoice->status->isIssued()) {
            throw ValidationException::withMessages([
                'status' => 'Nacrt se briše, ne stornira.',
            ]);
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'Dokument je već storniran.',
            ]);
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $user->getKey(),
            'cancel_reason' => $reason,
        ])->save();

        return $invoice->refresh();
    }
}
