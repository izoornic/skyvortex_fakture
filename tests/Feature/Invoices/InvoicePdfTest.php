<?php

namespace Tests\Feature\Invoices;

use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RenderInvoicePdf;
use App\Actions\Invoices\SaveInvoiceItems;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\InvoiceTotals;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $company = Company::factory()->create([
            'name' => 'Đurđević & Šišković d.o.o.',
            'city' => 'Niš',
        ]);
        BankAccount::factory()->primary()->create([
            'company_id' => $company->id,
            'account_number' => '160000000000000018',
        ]);

        $partner = Partner::factory()->create([
            'company_id' => $company->id,
            'name' => 'Čačanska privreda a.d.',
        ]);

        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        app(CurrentCompany::class)->set($company);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'bank_account_id' => $company->bankAccounts()->first()->id,
            'issue_date' => '2026-03-05',
            'note' => 'Hvala na saradnji.',
        ]);

        app(SaveInvoiceItems::class)->handle($invoice, [
            [
                'name' => 'Održavanje aplikacije',
                'description' => 'mart 2026',
                'unit_code' => 'MON', 'unit_symbol' => 'mes',
                'quantity' => 1, 'unit_price' => 85000,
                'discount_percent' => 0, 'vat_rate' => 20, 'vat_category' => 'S',
            ],
            [
                'name' => 'Licenca',
                'unit_code' => 'H87', 'unit_symbol' => 'kom',
                'quantity' => 3, 'unit_price' => 1200,
                'discount_percent' => 0, 'vat_rate' => 10, 'vat_category' => 'S',
            ],
        ]);

        $this->invoice = app(IssueInvoice::class)->handle($invoice->refresh());
    }

    public function test_the_template_carries_the_document_onto_paper(): void
    {
        $html = $this->renderTemplate();

        // Serbian diacritics must survive into the markup dompdf consumes.
        $this->assertStringContainsString('Đurđević &amp; Šišković d.o.o.', $html);
        $this->assertStringContainsString('Čačanska privreda a.d.', $html);
        $this->assertStringContainsString('Održavanje aplikacije', $html);

        $this->assertStringContainsString('2026-0001', $html);
        $this->assertStringContainsString($this->invoice->fullPaymentReference(), $html);
        $this->assertStringContainsString('160-0000000000000-18', $html);
        $this->assertStringContainsString('05.03.2026.', $html);
        $this->assertStringContainsString('Hvala na saradnji.', $html);
    }

    public function test_the_totals_on_paper_match_the_stored_ones(): void
    {
        $html = $this->renderTemplate();

        // 85000 + 3600 base, VAT 17000 + 360.
        $this->assertSame('88600.00', $this->invoice->subtotal);
        $this->assertSame('17360.00', $this->invoice->vat_total);
        $this->assertSame('105960.00', $this->invoice->total);

        $this->assertStringContainsString('105.960,00', $html);
        $this->assertStringContainsString('88.600,00', $html);
    }

    public function test_the_recapitulation_splits_by_rate(): void
    {
        $recapitulation = InvoiceTotals::recapitulation($this->invoice);

        $this->assertCount(2, $recapitulation);
        $this->assertSame(20.0, $recapitulation[0]['vat_rate']);
        $this->assertSame(85000.00, $recapitulation[0]['base']);
        $this->assertSame(17000.00, $recapitulation[0]['vat']);
        $this->assertSame(10.0, $recapitulation[1]['vat_rate']);
        $this->assertSame(360.00, $recapitulation[1]['vat']);

        $this->assertSame(
            (float) $this->invoice->vat_total,
            round($recapitulation->sum('vat'), 2),
        );
    }

    public function test_a_cancelled_document_says_so_on_paper(): void
    {
        app(CancelInvoice::class)
            ->handle($this->invoice, auth()->user(), 'Pogrešan iznos');

        $html = $this->renderTemplate();

        $this->assertStringContainsString('STORNIRANO', $html);
        $this->assertStringContainsString('Pogrešan iznos', $html);
    }

    public function test_dompdf_produces_a_file_with_the_fonts_embedded(): void
    {
        $output = app(RenderInvoicePdf::class)
            ->handle($this->invoice)
            ->output();

        $this->assertStringStartsWith('%PDF', $output);

        // Without an embedded unicode font, č/ć/š/ž/đ come out as blanks.
        $this->assertStringContainsString('DejaVuSans', $output);
    }

    private function renderTemplate(): string
    {
        $invoice = $this->invoice->fresh(['company.primaryBankAccount', 'partner', 'items', 'bankAccount']);

        return view('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'partner' => $invoice->partner,
            'account' => $invoice->bankAccount ?? $invoice->company->primaryBankAccount,
            'recapitulation' => InvoiceTotals::recapitulation($invoice),
        ])->render();
    }
}
