<?php

namespace Tests\Feature\Invoices;

use App\Actions\Invoices\CopyInvoiceToNextPeriod;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\SaveInvoiceItems;
use App\Enums\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\Company;
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
 * "Kopiraj prošli mesec" — for the clients that recur without a contract.
 */
class CopyInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        Carbon::setTestNow('2026-04-03 10:00:00');

        $this->company = Company::factory()->create();
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        $partner = Partner::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $partner->id,
            'period_year' => 2026,
            'period_month' => 3,
            'issue_date' => '2026-03-01',
            'supply_date' => '2026-03-31',
            'due_date' => '2026-03-16',
            'note' => 'Hvala na saradnji.',
        ]);

        app(SaveInvoiceItems::class)->handle($invoice, [
            [
                'name' => 'Održavanje', 'unit_code' => 'MON', 'unit_symbol' => 'mes',
                'quantity' => 1, 'unit_price' => 80000,
                'discount_percent' => 0, 'vat_rate' => 20, 'vat_category' => 'S',
            ],
        ]);

        $this->invoice = app(IssueInvoice::class)->handle($invoice->refresh());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_copy_is_a_draft_one_period_on(): void
    {
        $copy = app(CopyInvoiceToNextPeriod::class)->handle($this->invoice->load('items'));

        $this->assertSame(InvoiceStatus::Draft, $copy->status);
        $this->assertSame(2026, $copy->period_year);
        $this->assertSame(4, $copy->period_month);
        $this->assertSame('2026-04-03', $copy->issue_date->toDateString());
        $this->assertSame($this->invoice->id, $copy->source_invoice_id);
    }

    /**
     * A document supplied on the last day of its month keeps that meaning, not
     * the day number.
     */
    public function test_a_supply_date_on_the_last_day_stays_on_the_last_day(): void
    {
        $copy = app(CopyInvoiceToNextPeriod::class)->handle($this->invoice->load('items'));

        $this->assertSame('2026-04-30', $copy->supply_date->toDateString());
    }

    public function test_the_copy_keeps_the_lines_and_the_amounts(): void
    {
        $copy = app(CopyInvoiceToNextPeriod::class)->handle($this->invoice->load('items'));

        $this->assertCount(1, $copy->items);
        $this->assertSame('Održavanje', $copy->items->first()->name);
        $this->assertSame($this->invoice->total, $copy->total);
        $this->assertSame('Hvala na saradnji.', $copy->note);
    }

    /**
     * Everything that identifies the original document is left behind.
     */
    public function test_the_copy_takes_neither_the_number_nor_the_reference(): void
    {
        $copy = app(CopyInvoiceToNextPeriod::class)->handle($this->invoice->load('items'));

        $this->assertNull($copy->number);
        $this->assertNull($copy->payment_reference);
        $this->assertNull($copy->issued_at);
        $this->assertNotSame($this->invoice->id, $copy->id);
    }

    public function test_the_payment_term_is_carried_over_as_a_number_of_days(): void
    {
        $copy = app(CopyInvoiceToNextPeriod::class)->handle($this->invoice->load('items'));

        // The original was issued on 1 March and due on the 16th: fifteen days.
        $this->assertSame('2026-04-18', $copy->due_date->toDateString());
    }

    public function test_the_button_on_the_document_makes_the_copy(): void
    {
        Volt::test('invoices.show', ['invoice' => $this->invoice])
            ->call('copyToNextPeriod')
            ->assertRedirect();

        $this->assertSame(2, Invoice::acrossCompanies()->count());
        $this->assertSame(1, Invoice::acrossCompanies()->drafts()->count());
    }

    public function test_a_copy_of_a_copy_keeps_walking_forward(): void
    {
        $april = app(CopyInvoiceToNextPeriod::class)->handle($this->invoice->load('items'));
        $may = app(CopyInvoiceToNextPeriod::class)->handle($april->load('items'));

        $this->assertSame(5, $may->period_month);
        $this->assertSame($april->id, $may->source_invoice_id);
    }
}
