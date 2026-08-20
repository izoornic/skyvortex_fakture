<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Support\InvoiceTotals;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Str;

class RenderInvoicePdf
{
    public function handle(Invoice $invoice): PdfDocument
    {
        $invoice->loadMissing([
            'company.primaryBankAccount',
            'company.bankAccounts',
            'partner',
            'bankAccount',
            'items.vatExemptionReason',
            'vatExemptionReason',
        ]);

        return Pdf::setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->loadView('pdf.invoice', [
                'invoice' => $invoice,
                'company' => $invoice->company,
                'partner' => $invoice->partner,
                'account' => $invoice->bankAccount ?? $invoice->company->primaryBankAccount,
                'recapitulation' => InvoiceTotals::recapitulation($invoice),
            ]);
    }

    /**
     * File name for downloads and mail attachments.
     */
    public function fileName(Invoice $invoice): string
    {
        $number = Str::of($invoice->displayNumber())->replace(['/', '\\', ' '], '-');

        return "{$invoice->type->label()}-{$number}.pdf";
    }
}
