<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\RenderInvoicePdf;
use App\Models\Invoice;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InvoicePdfController extends Controller
{
    public function __construct(private readonly RenderInvoicePdf $renderer) {}

    public function __invoke(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        return $this->renderer
            ->handle($invoice)
            ->stream($this->renderer->fileName($invoice));
    }
}
