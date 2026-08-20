<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\PaymentReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a draft into an issued document: assigns the number, the payment
 * reference and the issue timestamp, all in one transaction.
 *
 * From here on the document is immutable — it is corrected with a credit note
 * or storniran, never edited.
 */
class IssueInvoice
{
    public function __construct(private readonly GenerateInvoiceNumber $numbers) {}

    public function handle(Invoice $invoice): Invoice
    {
        $this->guard($invoice);

        return DB::transaction(function () use ($invoice) {
            $assigned = $this->numbers->handle(
                $invoice->company,
                $invoice->type,
                $invoice->issue_date->year,
            );

            $invoice->forceFill([
                'number' => $assigned['number'],
                'number_year' => $assigned['year'],
                'number_sequence' => $assigned['sequence'],
                'payment_reference' => PaymentReference::forInvoiceNumber($assigned['number']),
                'payment_reference_model' => PaymentReference::MODEL,
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
            ])->save();

            return $invoice->refresh();
        });
    }

    /**
     * @throws ValidationException
     */
    private function guard(Invoice $invoice): void
    {
        $errors = [];

        if (! $invoice->status->isEditable()) {
            $errors['status'] = 'Dokument je već izdat.';
        }

        if ($invoice->items()->count() === 0) {
            $errors['items'] = 'Dokument bez stavki ne može biti izdat.';
        }

        if ($invoice->isForeignCurrency() && (float) $invoice->exchange_rate <= 0) {
            $errors['exchange_rate'] = 'Za stranu valutu mora biti unet kurs.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
