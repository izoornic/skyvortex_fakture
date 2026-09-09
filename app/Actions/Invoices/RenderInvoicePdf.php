<?php

namespace App\Actions\Invoices;

use App\Models\Company;
use App\Models\Invoice;
use App\Support\InvoiceTotals;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Str;

class RenderInvoicePdf
{
    /**
     * The logo of a company, read once per instance: a group bundle prints the
     * same issuer on every one of its pages.
     *
     * @var array<int, string|null>
     */
    private array $logos = [];

    public function __construct(private readonly RenderIpsQrCode $qrCode) {}

    public function handle(Invoice $invoice): PdfDocument
    {
        return Pdf::setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->loadView('pdf.invoice', $this->viewData($invoice));
    }

    /**
     * Everything one printed invoice needs. Assembled here rather than in the
     * view, so the single document and the group bundle print the same page.
     *
     * @return array<string, mixed>
     */
    public function viewData(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'company.primaryBankAccount',
            'company.bankAccounts',
            'partner',
            'bankAccount',
            'items.vatExemptionReason',
            'vatExemptionReason',
        ]);

        $account = $invoice->bankAccount ?? $invoice->company->primaryBankAccount;

        return [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'partner' => $invoice->partner,
            'account' => $account,
            'recapitulation' => InvoiceTotals::recapitulation($invoice),
            'exemptionReasons' => $invoice->exemptionReasons(),
            // dompdf never reaches out over the network, so the logo travels inline.
            'logo' => $this->logo($invoice->company),
            // Only an issued dinar document with an account and a reference can
            // be paid by scanning; everything else comes back null.
            'ipsQr' => $this->qrCode->handle($invoice, $account),
        ];
    }

    /**
     * File name for downloads and mail attachments.
     */
    public function fileName(Invoice $invoice): string
    {
        $number = Str::of($invoice->displayNumber())->replace(['/', '\\', ' '], '-');

        return "{$invoice->type->label()}-{$number}.pdf";
    }

    private function logo(Company $company): ?string
    {
        if (! array_key_exists($company->id, $this->logos)) {
            $this->logos[$company->id] = $company->logoDataUri();
        }

        return $this->logos[$company->id];
    }
}
