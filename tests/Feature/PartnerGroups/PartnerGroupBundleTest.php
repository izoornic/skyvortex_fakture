<?php

namespace Tests\Feature\PartnerGroups;

use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\SaveInvoiceItems;
use App\Actions\PartnerGroups\CollectGroupInvoices;
use App\Actions\PartnerGroups\RenderPartnerGroupBundlePdf;
use App\Mail\PartnerGroupBundleMail;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use RuntimeException;
use Tests\TestCase;

class PartnerGroupBundleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private PartnerGroup $group;

    private Partner $first;

    private Partner $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        Carbon::setTestNow('2026-04-10 09:00:00');

        $this->company = Company::factory()->create(['name' => 'Digital Skyvortex']);
        BankAccount::factory()->primary()->create([
            'company_id' => $this->company->id,
            'account_number' => '160000000000000018',
        ]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);

        $this->group = PartnerGroup::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Uprava Petrović',
            'email' => 'uprava@primer.rs',
        ]);

        $this->first = Partner::factory()->create([
            'company_id' => $this->company->id,
            'partner_group_id' => $this->group->id,
            'name' => 'SZ Nemanjina 12',
        ]);

        $this->second = Partner::factory()->create([
            'company_id' => $this->company->id,
            'partner_group_id' => $this->group->id,
            'name' => 'SZ Vojvode Stepe 4',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_bundle_carries_the_issued_invoices_of_the_group(): void
    {
        $one = $this->issuedInvoice($this->first, 2026, 3);
        $two = $this->issuedInvoice($this->second, 2026, 3);

        $collected = app(CollectGroupInvoices::class)->handle($this->group, 2026, 3);

        $this->assertSame(
            [$one->id, $two->id],
            $collected->pluck('id')->sort()->values()->all()
        );
    }

    public function test_a_draft_and_a_storno_stay_out_and_say_why(): void
    {
        $issued = $this->issuedInvoice($this->first, 2026, 3);
        $draft = $this->draft($this->second, 2026, 3);

        $cancelled = $this->issuedInvoice($this->second, 2026, 3);
        app(CancelInvoice::class)->handle($cancelled, auth()->user(), 'Greška u iznosu');

        $collector = app(CollectGroupInvoices::class);

        $this->assertSame([$issued->id], $collector->handle($this->group, 2026, 3)->pluck('id')->all());

        $excluded = $collector->excluded($this->group, 2026, 3);

        $this->assertCount(2, $excluded);
        $this->assertSame(
            [$draft->id, $cancelled->id],
            $excluded->pluck('invoice.id')->sort()->values()->all()
        );

        $reasons = $excluded->pluck('reason', 'invoice.id');

        $this->assertStringContainsString('Nacrt', $reasons[$draft->id]);
        $this->assertStringContainsString('Storno', $reasons[$cancelled->id]);
    }

    public function test_invoices_outside_the_group_the_period_and_the_company_stay_out(): void
    {
        $mine = $this->issuedInvoice($this->first, 2026, 3);

        // Same company, no group.
        $loner = Partner::factory()->create(['company_id' => $this->company->id]);
        $this->issuedInvoice($loner, 2026, 3);

        // Same company, another group.
        $otherGroup = PartnerGroup::factory()->create(['company_id' => $this->company->id]);
        $otherMember = Partner::factory()->create([
            'company_id' => $this->company->id,
            'partner_group_id' => $otherGroup->id,
        ]);
        $this->issuedInvoice($otherMember, 2026, 3);

        // Same group, another period.
        $this->issuedInvoice($this->second, 2026, 2);

        $collected = app(CollectGroupInvoices::class)->handle($this->group, 2026, 3);

        $this->assertSame([$mine->id], $collected->pluck('id')->all());
    }

    public function test_the_whole_year_is_taken_when_the_month_is_dropped(): void
    {
        $march = $this->issuedInvoice($this->first, 2026, 3);
        $february = $this->issuedInvoice($this->second, 2026, 2);
        $this->issuedInvoice($this->second, 2025, 12);

        $collected = app(CollectGroupInvoices::class)->handle($this->group, 2026);

        $this->assertEqualsCanonicalizing(
            [$february->id, $march->id],
            $collected->pluck('id')->all()
        );
    }

    public function test_the_document_opens_with_a_recapitulation_and_then_every_invoice(): void
    {
        $one = $this->issuedInvoice($this->first, 2026, 3);
        $two = $this->issuedInvoice($this->second, 2026, 3);

        $html = view(
            'pdf.partner-group-bundle',
            app(RenderPartnerGroupBundlePdf::class)->viewData($this->group, 2026, 3)
        )->render();

        $this->assertStringContainsString('Objedinjena pošiljka', $html);
        $this->assertStringContainsString('Uprava Petrović', $html);
        $this->assertStringContainsString('mart 2026.', $html);

        // Both invoices are listed on the cover and printed in full.
        $this->assertSame(2, substr_count($html, $one->number));
        $this->assertSame(2, substr_count($html, $two->number));

        $this->assertStringContainsString('SZ Nemanjina 12', $html);
        $this->assertStringContainsString('SZ Vojvode Stepe 4', $html);

        // One page break per invoice, so no two documents share a sheet.
        $this->assertSame(2, substr_count($html, 'class="document"'));

        // The recipient sees the total without adding up the attachment.
        $total = (float) $one->total + (float) $two->total;
        $this->assertStringContainsString(number_format($total, 2, ',', '.'), $html);
    }

    public function test_an_empty_period_produces_no_document(): void
    {
        $this->expectException(RuntimeException::class);

        app(RenderPartnerGroupBundlePdf::class)->viewData($this->group, 2026, 3);
    }

    public function test_the_route_streams_a_pdf_for_the_period(): void
    {
        $this->issuedInvoice($this->first, 2026, 3);

        $response = $this->get(route('partner-groups.pdf', [
            'partnerGroup' => $this->group,
            'god' => 2026,
            'mes' => 3,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_route_refuses_a_period_without_invoices(): void
    {
        $this->get(route('partner-groups.pdf', [
            'partnerGroup' => $this->group,
            'god' => 2026,
            'mes' => 3,
        ]))->assertNotFound();
    }

    public function test_the_route_does_not_reach_another_companys_group(): void
    {
        $other = Company::factory()->create();
        $foreign = PartnerGroup::factory()->create(['company_id' => $other->id]);

        $this->get(route('partner-groups.pdf', [
            'partnerGroup' => $foreign->id,
            'god' => 2026,
            'mes' => 3,
        ]))->assertNotFound();
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        auth()->logout();

        $this->get(route('partner-groups.pdf', [
            'partnerGroup' => $this->group->id,
            'god' => 2026,
            'mes' => 3,
        ]))->assertRedirect(route('login'));
    }

    public function test_the_bundle_is_mailed_to_the_group_with_one_attachment(): void
    {
        Mail::fake();

        $this->issuedInvoice($this->first, 2026, 3);

        Volt::test('partner-groups.bundle')
            ->set('groupId', (string) $this->group->id)
            ->set('year', 2026)
            ->set('month', 3)
            ->call('openSendModal')
            ->assertSet('recipient', 'uprava@primer.rs')
            ->call('send')
            ->assertHasNoErrors();

        Mail::assertSent(
            PartnerGroupBundleMail::class,
            fn (PartnerGroupBundleMail $mail) => $mail->hasTo('uprava@primer.rs')
                && count($mail->attachments()) === 1
                && $mail->group->is($this->group)
        );
    }

    public function test_an_empty_period_is_not_mailed(): void
    {
        Mail::fake();

        Volt::test('partner-groups.bundle')
            ->set('groupId', (string) $this->group->id)
            ->set('year', 2026)
            ->set('month', 3)
            ->set('recipient', 'uprava@primer.rs')
            ->call('send')
            ->assertHasErrors('bundle');

        Mail::assertNothingSent();
    }

    public function test_the_shipment_screen_shows_what_goes_in_and_what_does_not(): void
    {
        $issued = $this->issuedInvoice($this->first, 2026, 3);
        $this->draft($this->second, 2026, 3);

        Volt::test('partner-groups.bundle')
            ->set('groupId', (string) $this->group->id)
            ->set('year', 2026)
            ->set('month', 3)
            ->assertSee($issued->number)
            ->assertSee('SZ Nemanjina 12')
            ->assertSee('Izostavljeno iz pošiljke')
            ->assertSee('Nacrt još nije dokument.');
    }

    /**
     * The list hands over the period and nothing else: a status or a search
     * term must never quietly drop an invoice from the shipment.
     */
    public function test_the_invoice_list_offers_the_bundle_without_passing_its_filters(): void
    {
        $one = $this->issuedInvoice($this->first, 2026, 3);
        $two = $this->issuedInvoice($this->second, 2026, 3);

        Volt::test('invoices.index')
            ->set('year', 2026)
            ->set('month', 3)
            ->set('groupId', (string) $this->group->id)
            ->assertSee('Objedinjeni PDF (2)')
            ->assertSee($one->number)
            ->assertSee($two->number)
            // The search narrows the rows on screen, never the shipment.
            ->set('search', 'Nemanjina')
            ->assertDontSee($two->number)
            ->assertSee('Objedinjeni PDF (2)')
            ->set('search', '')
            ->set('status', 'placena')
            ->assertDontSee($one->number)
            ->assertSee('Objedinjeni PDF (2)');
    }

    public function test_the_invoice_list_filters_rows_by_group(): void
    {
        $mine = $this->issuedInvoice($this->first, 2026, 3);

        $loner = Partner::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Samostalni klijent d.o.o.',
        ]);
        $other = $this->issuedInvoice($loner, 2026, 3);

        Volt::test('invoices.index')
            ->set('year', 2026)
            ->set('month', 3)
            ->assertSee($other->number)
            ->set('groupId', (string) $this->group->id)
            ->assertSee($mine->number)
            ->assertDontSee($other->number);
    }

    private function draft(Partner $partner, int $year, int $month): Invoice
    {
        $invoice = Invoice::factory()->inPeriod($year, $month)->create([
            'company_id' => $this->company->id,
            'partner_id' => $partner->id,
            'bank_account_id' => $this->company->primaryBankAccount->id,
            'issue_date' => Carbon::create($year, $month, 28)->toDateString(),
            'supply_date' => Carbon::create($year, $month, 28)->endOfMonth()->toDateString(),
            'due_date' => Carbon::create($year, $month, 28)->addDays(15)->toDateString(),
        ]);

        app(SaveInvoiceItems::class)->handle($invoice, [
            [
                'name' => 'Održavanje',
                'unit_code' => 'MON', 'unit_symbol' => 'mes',
                'quantity' => 1, 'unit_price' => 12000,
                'discount_percent' => 0, 'vat_rate' => 20, 'vat_category' => 'S',
            ],
        ]);

        return $invoice->refresh();
    }

    private function issuedInvoice(Partner $partner, int $year, int $month): Invoice
    {
        return app(IssueInvoice::class)->handle($this->draft($partner, $year, $month));
    }
}
