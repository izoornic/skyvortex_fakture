<?php

namespace Tests\Feature\Invoices;

use App\Actions\Contracts\GenerateContractDrafts;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RenderInvoicePdf;
use App\Actions\Invoices\SaveInvoiceItems;
use App\Enums\VatCategory;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A company outside the VAT system charges no VAT, and every document it issues
 * has to say under which article. Left to be picked by hand it stayed empty on
 * all of them: the PDF printed no legal basis and SEF would reject the invoice
 * for a missing BT-120 / BT-121.
 *
 * So the basis is set once on the company and flows down — document, then line
 * — and issuing a document that still has none is refused.
 */
class VatExemptionBasisTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Partner $partner;

    private VatExemptionReason $reason;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->reason = VatExemptionReason::create([
            'code' => 'PDV-RS-33',
            'vat_category' => VatCategory::OutOfScope,
            'description' => 'Promet nije predmet oporezivanja PDV-om',
            'legal_basis' => 'član 33. Zakona o PDV',
        ]);

        $this->company = Company::factory()->outsideVatSystem()->create([
            'name' => 'Paušalac d.o.o.',
            'vat_exemption_reason_id' => $this->reason->id,
        ]);

        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        $this->partner = Partner::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_a_new_document_starts_with_the_basis_of_its_company(): void
    {
        Volt::test('invoices.form')->assertSet('vat_exemption_reason_id', $this->reason->id);
    }

    public function test_a_line_that_charges_no_vat_inherits_the_document_basis(): void
    {
        $invoice = $this->draft();

        app(SaveInvoiceItems::class)->handle($invoice, [
            $this->line('Održavanje', VatCategory::OutOfScope),
        ]);

        $this->assertSame(
            $this->reason->id,
            $invoice->refresh()->items->first()->vat_exemption_reason_id,
        );
    }

    public function test_a_standard_rate_line_is_left_without_a_basis(): void
    {
        $invoice = $this->draft();

        app(SaveInvoiceItems::class)->handle($invoice, [
            ['vat_rate' => 20] + $this->line('Licenca', VatCategory::Standard),
        ]);

        $this->assertNull($invoice->refresh()->items->first()->vat_exemption_reason_id);
    }

    public function test_a_basis_chosen_on_the_line_wins_over_the_document(): void
    {
        $other = VatExemptionReason::create([
            'code' => 'PDV-RS-24',
            'vat_category' => VatCategory::Exempt,
            'description' => 'Oslobođeno bez prava na odbitak',
        ]);

        $invoice = $this->draft();

        app(SaveInvoiceItems::class)->handle($invoice, [
            ['vat_exemption_reason_id' => $other->id] + $this->line('Zakup', VatCategory::Exempt),
        ]);

        $this->assertSame($other->id, $invoice->refresh()->items->first()->vat_exemption_reason_id);
    }

    public function test_a_document_whose_line_has_no_basis_cannot_be_issued(): void
    {
        $company = Company::factory()->outsideVatSystem()->create(['vat_exemption_reason_id' => null]);
        BankAccount::factory()->primary()->create(['company_id' => $company->id]);
        $partner = Partner::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
        ]);

        app(SaveInvoiceItems::class)->handle($invoice, [
            $this->line('Održavanje', VatCategory::OutOfScope),
        ]);

        $this->expectException(ValidationException::class);

        app(IssueInvoice::class)->handle($invoice->refresh());
    }

    public function test_a_document_with_a_basis_is_issued_normally(): void
    {
        $invoice = $this->draft();

        app(SaveInvoiceItems::class)->handle($invoice, [
            $this->line('Održavanje', VatCategory::OutOfScope),
        ]);

        $issued = app(IssueInvoice::class)->handle($invoice->refresh());

        $this->assertNotNull($issued->number);
    }

    public function test_the_pdf_prints_the_legal_basis(): void
    {
        $invoice = $this->draft();

        app(SaveInvoiceItems::class)->handle($invoice, [
            $this->line('Održavanje', VatCategory::OutOfScope),
        ]);

        $issued = app(IssueInvoice::class)->handle($invoice->refresh());

        $html = view('pdf.invoice', app(RenderInvoicePdf::class)->viewData($issued))->render();

        $this->assertStringContainsString('Izdavalac nije u sistemu PDV-a.', $html);
        $this->assertStringContainsString('Promet nije predmet oporezivanja PDV-om', $html);
        $this->assertStringContainsString('član 33. Zakona o PDV', $html);
    }

    public function test_each_basis_is_printed_once_however_many_lines_use_it(): void
    {
        $invoice = $this->draft();

        app(SaveInvoiceItems::class)->handle($invoice, [
            $this->line('Održavanje', VatCategory::OutOfScope),
            $this->line('Hosting', VatCategory::OutOfScope),
        ]);

        $this->assertCount(1, $invoice->refresh()->exemptionReasons());
    }

    public function test_a_draft_made_from_a_contract_carries_the_basis(): void
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'generation_day' => 1,
            'starts_on' => '2026-01-01',
            'ends_on' => null,
        ]);

        ContractItem::factory()->create([
            'contract_id' => $contract->id,
            'vat_rate' => 0,
            'vat_category' => VatCategory::OutOfScope,
            'vat_exemption_reason_id' => null,
        ]);

        $draft = app(GenerateContractDrafts::class)->draftFor($contract, Carbon::parse('2026-04-01'));

        $this->assertSame($this->reason->id, $draft->vat_exemption_reason_id);
        $this->assertSame($this->reason->id, $draft->items->first()->vat_exemption_reason_id);
    }

    public function test_the_contract_form_keeps_the_basis_chosen_on_a_line(): void
    {
        Volt::test('contracts.form')
            ->set('partner_id', $this->partner->id)
            ->set('name', 'Održavanje portala')
            ->set('items.0.name', 'Mesečno održavanje')
            ->set('items.0.unit_price', '25000')
            ->set('items.0.vat_exemption_reason_id', $this->reason->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            $this->reason->id,
            Contract::firstWhere('name', 'Održavanje portala')->items->first()->vat_exemption_reason_id,
        );
    }

    private function draft(): Invoice
    {
        return Invoice::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'vat_exemption_reason_id' => $this->company->vat_exemption_reason_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(string $name, VatCategory $category): array
    {
        return [
            'name' => $name,
            'unit_code' => 'MON',
            'unit_symbol' => 'mes',
            'quantity' => 1,
            'unit_price' => 25000,
            'discount_percent' => 0,
            'vat_rate' => 0,
            'vat_category' => $category->value,
        ];
    }
}
