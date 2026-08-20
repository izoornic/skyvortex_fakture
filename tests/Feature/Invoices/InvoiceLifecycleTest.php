<?php

namespace Tests\Feature\Invoices;

use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\GenerateInvoiceNumber;
use App\Actions\Invoices\IssueInvoice;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceNumberSequence;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\PaymentReference;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Partner $partner;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create(['pib' => '100000001']);
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);

        $this->partner = Partner::factory()->create(['company_id' => $this->company->id]);

        $this->user = User::factory()->bookkeeper()->create();
        $this->user->companies()->attach($this->company);

        $this->actingAs($this->user);
        app(CurrentCompany::class)->set($this->company);
    }

    private function draft(array $attributes = []): Invoice
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            ...$attributes,
        ]);

        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        return $invoice->refresh();
    }

    public function test_issuing_assigns_number_reference_and_timestamp(): void
    {
        $invoice = $this->draft(['issue_date' => '2026-03-05']);

        $issued = app(IssueInvoice::class)->handle($invoice);

        $this->assertSame('2026-0001', $issued->number);
        $this->assertSame(2026, $issued->number_year);
        $this->assertSame(1, $issued->number_sequence);
        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertNotNull($issued->issued_at);
        $this->assertSame(PaymentReference::forInvoiceNumber('2026-0001'), $issued->payment_reference);
        $this->assertTrue(PaymentReference::isValid($issued->payment_reference));
    }

    public function test_numbering_continues_from_the_value_entered_at_company_setup(): void
    {
        InvoiceNumberSequence::acrossCompanies()->create([
            'company_id' => $this->company->id,
            'type' => DocumentType::Invoice,
            'year' => 2026,
            'last_number' => 17,
        ]);

        $issued = app(IssueInvoice::class)->handle($this->draft(['issue_date' => '2026-03-05']));

        $this->assertSame('2026-0018', $issued->number);
    }

    public function test_each_document_type_has_its_own_counter_and_prefix(): void
    {
        $invoice = app(IssueInvoice::class)->handle(
            $this->draft(['issue_date' => '2026-03-05', 'type' => DocumentType::Invoice])
        );

        $creditNote = app(IssueInvoice::class)->handle(
            $this->draft(['issue_date' => '2026-03-05', 'type' => DocumentType::CreditNote])
        );

        $this->assertSame('2026-0001', $invoice->number);
        $this->assertSame('KO-2026-0001', $creditNote->number);
    }

    public function test_numbers_restart_each_year(): void
    {
        $first = app(IssueInvoice::class)->handle($this->draft(['issue_date' => '2026-12-31']));
        $second = app(IssueInvoice::class)->handle($this->draft(['issue_date' => '2027-01-02']));

        $this->assertSame('2026-0001', $first->number);
        $this->assertSame('2027-0001', $second->number);
    }

    public function test_numbers_are_handed_out_one_at_a_time(): void
    {
        $numbers = [];

        for ($i = 0; $i < 5; $i++) {
            $numbers[] = app(IssueInvoice::class)->handle($this->draft(['issue_date' => '2026-03-05']))->number;
        }

        $this->assertSame(
            ['2026-0001', '2026-0002', '2026-0003', '2026-0004', '2026-0005'],
            $numbers
        );
        $this->assertSame($numbers, array_unique($numbers));
    }

    public function test_a_document_without_items_cannot_be_issued(): void
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
        ]);

        $this->expectException(ValidationException::class);

        app(IssueInvoice::class)->handle($invoice);
    }

    public function test_a_foreign_currency_document_needs_a_rate(): void
    {
        $invoice = $this->draft(['currency' => 'EUR', 'exchange_rate' => 0]);

        $this->expectException(ValidationException::class);

        app(IssueInvoice::class)->handle($invoice);
    }

    public function test_an_issued_document_can_no_longer_be_issued_or_edited(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft());

        $this->assertFalse($invoice->isEditable());
        $this->assertFalse($this->user->can('update', $invoice));
        $this->assertFalse($this->user->can('issue', $invoice));

        $this->expectException(ValidationException::class);

        app(IssueInvoice::class)->handle($invoice);
    }

    public function test_cancelling_records_who_when_and_why_and_keeps_the_number(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft());
        $number = $invoice->number;

        $cancelled = app(CancelInvoice::class)->handle($invoice, $this->user, 'Pogrešan partner');

        $this->assertSame(InvoiceStatus::Cancelled, $cancelled->status);
        $this->assertSame($number, $cancelled->number);
        $this->assertSame($this->user->id, $cancelled->cancelled_by);
        $this->assertSame('Pogrešan partner', $cancelled->cancel_reason);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_every_role_may_cancel_without_approval(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft());

        $this->assertTrue($this->user->can('cancel', $invoice));
    }

    public function test_a_draft_is_deleted_not_cancelled(): void
    {
        $invoice = $this->draft();

        $this->assertFalse($this->user->can('cancel', $invoice));

        $this->expectException(ValidationException::class);

        app(CancelInvoice::class)->handle($invoice, $this->user, 'Bilo šta');
    }

    public function test_a_cancelled_document_is_left_out_of_revenue(): void
    {
        $keep = app(IssueInvoice::class)->handle($this->draft());
        $drop = app(IssueInvoice::class)->handle($this->draft());

        app(CancelInvoice::class)->handle($drop, $this->user, 'Greška');

        $this->assertSame(1, Invoice::query()->countable()->count());
        $this->assertSame($keep->id, Invoice::query()->countable()->first()->id);
    }

    public function test_the_form_saves_a_draft_with_computed_totals(): void
    {
        Volt::test('invoices.form')
            ->set('partner_id', $this->partner->id)
            ->set('items', [[
                'name' => 'Održavanje',
                'description' => '',
                'unit_code' => 'MON',
                'quantity' => '2',
                'unit_price' => '25000',
                'discount_percent' => '0',
                'vat_rate' => '20',
                'vat_category' => 'S',
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::firstWhere('partner_id', $this->partner->id);

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->number);
        $this->assertSame('50000.00', $invoice->subtotal);
        $this->assertSame('10000.00', $invoice->vat_total);
        $this->assertSame('60000.00', $invoice->total);
        $this->assertSame('mes', $invoice->items->first()->unit_symbol);
    }

    public function test_the_form_refuses_a_document_without_items(): void
    {
        Volt::test('invoices.form')
            ->set('partner_id', $this->partner->id)
            ->set('items', [])
            ->call('save')
            ->assertHasErrors('items');
    }

    public function test_the_form_refuses_a_partner_of_another_company(): void
    {
        $foreign = Partner::factory()->create(['company_id' => Company::factory()->create()->id]);

        Volt::test('invoices.form')
            ->set('partner_id', $foreign->id)
            ->call('save')
            ->assertHasErrors('partner_id');
    }

    public function test_foreign_currency_totals_are_converted_to_dinars(): void
    {
        Volt::test('invoices.form')
            ->set('partner_id', $this->partner->id)
            ->set('currency', 'EUR')
            ->set('exchange_rate', '117.5')
            ->set('items', [[
                'name' => 'Konsalting',
                'description' => '',
                'unit_code' => 'HUR',
                'quantity' => '10',
                'unit_price' => '100',
                'discount_percent' => '0',
                'vat_rate' => '20',
                'vat_category' => 'S',
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::firstWhere('currency', 'EUR');

        $this->assertSame('1000.00', $invoice->subtotal);
        $this->assertSame('1200.00', $invoice->total);
        $this->assertSame('141000.00', $invoice->total_rsd);
    }

    public function test_invoices_are_scoped_to_the_active_company(): void
    {
        $other = Company::factory()->create();
        $otherPartner = Partner::factory()->create(['company_id' => $other->id]);

        $this->draft();
        Invoice::factory()->count(2)->create([
            'company_id' => $other->id,
            'partner_id' => $otherPartner->id,
        ]);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(3, Invoice::acrossCompanies()->count());
    }

    public function test_the_monthly_list_only_shows_the_chosen_period(): void
    {
        $this->draft(['period_year' => 2026, 'period_month' => 3, 'issue_date' => '2026-03-01']);
        $this->draft(['period_year' => 2026, 'period_month' => 4, 'issue_date' => '2026-04-01']);

        Volt::test('invoices.index')
            ->set('year', 2026)
            ->set('month', 3)
            ->assertSee('01.03.2026.')
            ->assertDontSee('01.04.2026.');
    }

    public function test_sending_attaches_the_pdf_and_stamps_the_document(): void
    {
        Mail::fake();

        $invoice = app(IssueInvoice::class)->handle($this->draft());

        Volt::test('invoices.show', ['invoice' => $invoice])
            ->set('recipient', 'kupac@example.test')
            ->call('send')
            ->assertHasNoErrors();

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('kupac@example.test'));

        $this->assertNotNull($invoice->fresh()->sent_at);
    }

    public function test_the_pdf_route_returns_a_pdf(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft());

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_bookkeeper_cannot_open_an_invoice_of_another_company(): void
    {
        $other = Company::factory()->create();
        $otherPartner = Partner::factory()->create(['company_id' => $other->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $other->id,
            'partner_id' => $otherPartner->id,
        ]);

        $this->get(route('invoices.show', $invoice))->assertNotFound();
    }

    public function test_issuing_is_recorded_in_the_audit_trail(): void
    {
        $invoice = app(IssueInvoice::class)->handle($this->draft());

        $events = $invoice->auditLogs()->pluck('event');

        $this->assertTrue($events->contains('updated'));
        $this->assertSame(
            $this->user->id,
            $invoice->auditLogs()->where('event', 'updated')->first()->user_id
        );
    }

    public function test_the_sequence_row_is_locked_while_a_number_is_taken(): void
    {
        // The action refuses to run outside a transaction, which is what makes
        // the lock meaningful in the first place.
        DB::transaction(function () {
            $first = app(GenerateInvoiceNumber::class)->handle($this->company, DocumentType::Invoice, 2026);
            $second = app(GenerateInvoiceNumber::class)->handle($this->company, DocumentType::Invoice, 2026);

            $this->assertSame('2026-0001', $first['number']);
            $this->assertSame('2026-0002', $second['number']);
        });
    }
}
