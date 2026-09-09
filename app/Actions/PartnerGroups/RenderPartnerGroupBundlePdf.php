<?php

namespace App\Actions\PartnerGroups;

use App\Actions\Invoices\RenderInvoicePdf;
use App\Models\Invoice;
use App\Models\PartnerGroup;
use App\Support\PeriodLabel;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * One PDF for a group: a cover recapitulation, then every invoice of the
 * group's partners for the period, each on its own page.
 *
 * The invoices are drawn, not merged. dompdf cannot join finished PDFs, and
 * merging would mean another dependency; drawing them through the same partial
 * keeps the pages identical to the ones sent out one by one.
 */
class RenderPartnerGroupBundlePdf
{
    public function __construct(
        private readonly CollectGroupInvoices $collector,
        private readonly RenderInvoicePdf $invoiceRenderer,
    ) {}

    public function handle(PartnerGroup $group, int $year, ?int $month = null): PdfDocument
    {
        return Pdf::setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->loadView('pdf.partner-group-bundle', $this->viewData($group, $year, $month));
    }

    /**
     * Everything the bundle prints, assembled before rendering starts.
     *
     * @return array<string, mixed>
     */
    public function viewData(PartnerGroup $group, int $year, ?int $month = null): array
    {
        $invoices = $this->collector->handle($group, $year, $month);

        if ($invoices->isEmpty()) {
            throw new RuntimeException(
                "Grupa „{$group->name}” nema izdatih faktura za period ".PeriodLabel::for($year, $month).'.'
            );
        }

        return [
            'group' => $group,
            'company' => $group->company,
            'documents' => $this->documents($invoices),
            'invoices' => $invoices,
            'periodLabel' => PeriodLabel::for($year, $month),
            'totals' => $this->totalsByCurrency($invoices),
            'totalRsd' => (float) $invoices->sum('total_rsd'),
        ];
    }

    /**
     * File name for downloads and mail attachments.
     */
    public function fileName(PartnerGroup $group, int $year, ?int $month = null): string
    {
        $period = $month ? sprintf('%04d-%02d', $year, $month) : (string) $year;

        return 'Fakture-'.Str::slug($group->name).'-'.$period.'.pdf';
    }

    /**
     * The view data of every invoice, assembled before rendering starts.
     *
     * A single bad document must say which one it is: inside one dompdf pass
     * the failure would surface as a template error with no number in it.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return list<array<string, mixed>>
     */
    private function documents($invoices): array
    {
        return $invoices->map(function (Invoice $invoice) {
            try {
                return $this->invoiceRenderer->viewData($invoice);
            } catch (Throwable $error) {
                throw new RuntimeException(
                    "Faktura {$invoice->displayNumber()} ({$invoice->partner->name}) ne može da se iscrta: {$error->getMessage()}",
                    previous: $error,
                );
            }
        })->all();
    }

    /**
     * Totals per currency: a group may hold invoices in more than one, and a
     * single sum across them would be a made-up number.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<string, float>
     */
    private function totalsByCurrency($invoices): array
    {
        return $invoices
            ->groupBy('currency')
            ->map(fn ($group) => (float) $group->sum('total'))
            ->all();
    }
}
