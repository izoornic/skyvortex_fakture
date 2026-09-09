<?php

namespace Tests\Feature\Invoices;

use App\Actions\Contracts\GenerateContractDrafts;
use App\Actions\Invoices\CopyInvoiceToNextPeriod;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RenderInvoicePdf;
use App\Actions\Invoices\SaveInvoiceItems;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A document sent by mail carries no seal and no signature, so it has to say
 * that it is valid without them. The choice is made per document, and a
 * contract passes it on to every draft it makes.
 */
class ValidWithoutSignatureTest extends TestCase
{
    private const NOTICE = 'Ova faktura je validna u elektronskom obliku bez pečata i potpisa!';

    use RefreshDatabase;

    private Company $company;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        Carbon::setTestNow('2026-04-10 09:00:00');

        $this->company = Company::factory()->create();
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        $this->partner = Partner::factory()->create([
            'company_id' => $this->company->id,
            'payment_days' => 15,
            'default_currency' => 'RSD',
        ]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_invoice_form_records_the_choice(): void
    {
        Volt::test('invoices.form')
            ->set('partner_id', $this->partner->id)
            ->set('valid_without_signature', true)
            ->set('items.0.name', 'Održavanje')
            ->set('items.0.unit_price', '25000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Invoice::acrossCompanies()->sole()->valid_without_signature);
    }

    /**
     * Documents leave this house by mail, so the mark is on by default and is
     * taken off by hand for the rare document that gets a seal.
     */
    public function test_a_new_document_is_marked_by_default(): void
    {
        Volt::test('invoices.form')
            ->assertSet('valid_without_signature', true)
            ->set('partner_id', $this->partner->id)
            ->set('items.0.name', 'Održavanje')
            ->set('items.0.unit_price', '25000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Invoice::acrossCompanies()->sole()->valid_without_signature);
    }

    public function test_the_mark_can_be_taken_off(): void
    {
        Volt::test('invoices.form')
            ->set('partner_id', $this->partner->id)
            ->set('valid_without_signature', false)
            ->set('items.0.name', 'Održavanje')
            ->set('items.0.unit_price', '25000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(Invoice::acrossCompanies()->sole()->valid_without_signature);
    }

    public function test_a_new_contract_is_marked_by_default(): void
    {
        Volt::test('contracts.form')->assertSet('valid_without_signature', true);
    }

    public function test_the_form_shows_the_choice_of_an_existing_document(): void
    {
        $invoice = $this->draft(['valid_without_signature' => true]);

        Volt::test('invoices.form', ['invoice' => $invoice])
            ->assertSet('valid_without_signature', true);
    }

    public function test_the_pdf_prints_the_notice_instead_of_the_signature_line(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft(['valid_without_signature' => true]));

        $html = view('pdf.invoice', app(RenderInvoicePdf::class)->viewData($invoice))->render();

        $this->assertStringContainsString(self::NOTICE, $html);
        $this->assertStringNotContainsString('Potpis i pečat', $html);
    }

    public function test_an_unmarked_document_keeps_the_signature_line(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft(['valid_without_signature' => false]));

        $html = view('pdf.invoice', app(RenderInvoicePdf::class)->viewData($invoice))->render();

        $this->assertStringNotContainsString(self::NOTICE, $html);
        $this->assertStringContainsString('Potpis i pečat', $html);
    }

    public function test_the_contract_form_records_the_choice(): void
    {
        Volt::test('contracts.form')
            ->set('partner_id', $this->partner->id)
            ->set('name', 'Održavanje aplikacije')
            ->set('starts_on', '2026-01-01')
            ->set('valid_without_signature', true)
            ->set('items.0.name', 'Mesečno održavanje')
            ->set('items.0.unit_price', '25000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Contract::acrossCompanies()->sole()->valid_without_signature);
    }

    public function test_a_draft_made_from_a_contract_carries_the_choice(): void
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'generation_day' => 1,
            'starts_on' => '2026-01-01',
            'ends_on' => null,
            'valid_without_signature' => true,
        ]);

        ContractItem::factory()->create(['contract_id' => $contract->id]);

        $draft = app(GenerateContractDrafts::class)->draftFor($contract, Carbon::parse('2026-04-01'));

        $this->assertTrue($draft->valid_without_signature);
    }

    public function test_the_copy_into_the_next_period_carries_the_choice(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft(['valid_without_signature' => true]));

        $copy = app(CopyInvoiceToNextPeriod::class)->handle($invoice->refresh());

        $this->assertTrue($copy->valid_without_signature);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function draft(array $attributes = []): Invoice
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'bank_account_id' => $this->company->primaryBankAccount->id,
            ...$attributes,
        ]);

        app(SaveInvoiceItems::class)->handle($invoice, [[
            'name' => 'Održavanje',
            'unit_code' => 'MON', 'unit_symbol' => 'mes',
            'quantity' => 1, 'unit_price' => 25000,
            'discount_percent' => 0, 'vat_rate' => 20, 'vat_category' => 'S',
        ]]);

        return $invoice->refresh();
    }
}
