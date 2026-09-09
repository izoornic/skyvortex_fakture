<?php

namespace App\Http\Controllers;

use App\Actions\PartnerGroups\RenderPartnerGroupBundlePdf;
use App\Models\PartnerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * One PDF with every issued invoice of a group for a period.
 *
 * The period is the only thing taken from the caller. Status and search never
 * enter the document — a term typed into the invoice list must not quietly drop
 * an invoice from the shipment.
 */
class PartnerGroupBundlePdfController extends Controller
{
    public function __construct(private readonly RenderPartnerGroupBundlePdf $renderer) {}

    public function __invoke(Request $request, PartnerGroup $partnerGroup): Response
    {
        Gate::authorize('view', $partnerGroup);

        $period = $request->validate([
            'god' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) $period['god'];
        $month = isset($period['mes']) ? (int) $period['mes'] : null;

        try {
            $pdf = $this->renderer->handle($partnerGroup, $year, $month);
        } catch (RuntimeException $error) {
            abort(404, $error->getMessage());
        }

        return $pdf->stream($this->renderer->fileName($partnerGroup, $year, $month));
    }
}
