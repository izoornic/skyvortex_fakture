<?php

namespace Tests\Feature\Contracts;

use App\Actions\Contracts\GenerateContractDrafts;
use App\Enums\InvoiceStatus;
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
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The month a contract bills is the whole point of M3: get it wrong and the tax
 * period on a real document is wrong with it.
 */
class GenerateContractDraftsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create(['city' => 'Pančevo']);
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        $this->partner = Partner::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_a_contract_billed_in_arrears_invoices_the_month_that_has_passed(): void
    {
        $this->contract();

        $invoice = $this->runOn('2026-04-01')['created']->first();

        $this->assertSame(2026, $invoice->period_year);
        $this->assertSame(3, $invoice->period_month);
        $this->assertSame('2026-03-31', $invoice->supply_date->toDateString());
        $this->assertSame('2026-04-01', $invoice->issue_date->toDateString());
    }

    public function test_a_contract_billed_in_advance_invoices_the_month_that_is_starting(): void
    {
        $this->contract(fn ($factory) => $factory->billedInAdvance());

        $invoice = $this->runOn('2026-04-01')['created']->first();

        $this->assertSame(4, $invoice->period_month);
        $this->assertSame('2026-04-01', $invoice->supply_date->toDateString());
    }

    /**
     * A month of lag: the September run bills July, not August.
     */
    public function test_a_contract_billed_two_months_in_arrears_invoices_the_month_before_last(): void
    {
        $this->contract(fn ($factory) => $factory->billedTwoMonthsInArrears());

        $invoice = $this->runOn('2026-09-01')['created']->first();

        $this->assertSame(2026, $invoice->period_year);
        $this->assertSame(7, $invoice->period_month);
        $this->assertSame('2026-07-31', $invoice->supply_date->toDateString());
        $this->assertSame('2026-09-01', $invoice->issue_date->toDateString());
    }

    /**
     * The lag also moves the first run: a contract starting in April is not
     * billed until June, because May's run would bill March.
     */
    public function test_a_contract_billed_two_months_in_arrears_waits_for_its_first_period(): void
    {
        $this->contract(fn ($factory) => $factory->billedTwoMonthsInArrears()->running('2026-04-01'));

        $this->assertCount(0, $this->runOn('2026-05-01')['created'], 'Fakturisan je mart, pre početka ugovora.');

        $invoice = $this->runOn('2026-06-01')['created']->first();

        $this->assertNotNull($invoice);
        $this->assertSame(4, $invoice->period_month);
    }

    /**
     * And it moves the last run just as far: March is billed in May, two months
     * after the contract is over.
     */
    public function test_a_finished_contract_billed_two_months_in_arrears_still_bills_its_last_month(): void
    {
        $this->contract(fn ($factory) => $factory->billedTwoMonthsInArrears()->running('2026-01-01', '2026-03-31'));

        $this->assertSame(2, $this->runOn('2026-04-01')['created']->first()->period_month);

        $invoice = $this->runOn('2026-05-01')['created']->first();

        $this->assertNotNull($invoice, 'Poslednji mesec ugovora nije fakturisan.');
        $this->assertSame(3, $invoice->period_month);

        // And nothing after that.
        $this->assertCount(0, $this->runOn('2026-06-01')['created']);
    }

    public function test_the_draft_carries_the_contract_terms_and_lines(): void
    {
        $contract = $this->contract();

        $invoice = $this->runOn('2026-04-01')['created']->first();

        $this->assertSame($contract->id, $invoice->contract_id);
        $this->assertSame($this->partner->id, $invoice->partner_id);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('Pančevo', $invoice->place_of_issue);
        $this->assertSame('2026-04-16', $invoice->due_date->toDateString());

        $this->assertCount(1, $invoice->items);
        $this->assertSame('Održavanje aplikacije', $invoice->items->first()->name);

        // 1 x 50000, 20% VAT.
        $this->assertSame('50000.00', $invoice->subtotal);
        $this->assertSame('10000.00', $invoice->vat_total);
        $this->assertSame('60000.00', $invoice->total);
    }

    public function test_a_second_run_in_the_same_month_makes_nothing(): void
    {
        $this->contract();

        $this->assertCount(1, $this->runOn('2026-04-01')['created']);
        $this->assertCount(0, $this->runOn('2026-04-01')['created']);
        $this->assertCount(0, $this->runOn('2026-04-20')['created']);

        $this->assertSame(1, Invoice::acrossCompanies()->count());
    }

    public function test_a_missed_day_is_still_caught_later_in_the_month(): void
    {
        $this->contract(fn ($factory) => $factory->generatingOn(5));

        $this->assertCount(0, $this->runOn('2026-04-04')['created']);
        $this->assertCount(1, $this->runOn('2026-04-11')['created']);
    }

    public function test_a_contract_set_to_the_thirty_first_still_runs_in_february(): void
    {
        $this->contract(fn ($factory) => $factory->generatingOn(31)->billedInAdvance());

        $invoice = $this->runOn('2026-02-28')['created']->first();

        $this->assertNotNull($invoice);
        $this->assertSame(2, $invoice->period_month);
    }

    /**
     * A contract that ended in March still owes its March invoice, and that one
     * is made in April — after the contract is over.
     */
    public function test_a_finished_contract_still_bills_its_last_month(): void
    {
        $this->contract(fn ($factory) => $factory->running('2026-01-01', '2026-03-31'));

        $invoice = $this->runOn('2026-04-01')['created']->first();

        $this->assertNotNull($invoice, 'Poslednji mesec ugovora nije fakturisan.');
        $this->assertSame(3, $invoice->period_month);

        // And nothing after that.
        $this->assertCount(0, $this->runOn('2026-05-01')['created']);
    }

    public function test_a_contract_does_not_bill_a_month_before_it_started(): void
    {
        $this->contract(fn ($factory) => $factory->running('2026-04-01'));

        $this->assertCount(0, $this->runOn('2026-04-01')['created'], 'Fakturisan je mart, pre početka ugovora.');
        $this->assertCount(1, $this->runOn('2026-05-01')['created']);
    }

    public function test_an_inactive_contract_produces_nothing(): void
    {
        $this->contract(fn ($factory) => $factory->inactive());

        $this->assertCount(0, $this->runOn('2026-04-01')['created']);
    }

    public function test_a_contract_without_lines_is_reported_not_silently_skipped(): void
    {
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'name' => 'Prazan ugovor',
            'starts_on' => '2026-01-01',
        ]);

        $result = $this->runOn('2026-04-01');

        $this->assertCount(0, $result['created']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('nema stavki', $result['skipped']->first()['reason']);
    }

    /**
     * A foreign-currency draft arriving with a rate of 1 would quietly turn a
     * 1000 EUR invoice into 1000 RSD of revenue.
     */
    public function test_a_foreign_currency_draft_takes_the_last_known_rate(): void
    {
        Invoice::factory()
            ->forCompany($this->company)
            ->inCurrency('EUR', 117.25)
            ->create(['issued_at' => now(), 'status' => InvoiceStatus::Issued, 'issue_date' => '2026-03-15']);

        $this->contract(fn ($factory) => $factory->state(['currency' => 'EUR']));

        $invoice = $this->runOn('2026-04-01')['created']->first();

        $this->assertSame('EUR', $invoice->currency);
        $this->assertSame('117.250000', $invoice->exchange_rate);
    }

    public function test_the_preview_says_what_the_run_would_do_without_doing_it(): void
    {
        $this->contract();

        $preview = app(GenerateContractDrafts::class)->preview($this->company, Carbon::parse('2026-04-01'));

        $this->assertCount(1, $preview);
        $this->assertSame(3, $preview->first()['period']->month);
        $this->assertSame(60000.0, $preview->first()['total']);
        $this->assertSame(0, Invoice::acrossCompanies()->count(), 'Pregled je napravio dokument.');
    }

    public function test_contracts_of_another_company_are_untouched(): void
    {
        $this->contract();

        $other = Company::factory()->create();
        $otherPartner = Partner::factory()->create(['company_id' => $other->id]);
        $otherContract = Contract::factory()->create([
            'company_id' => $other->id,
            'partner_id' => $otherPartner->id,
            'starts_on' => '2026-01-01',
        ]);
        ContractItem::factory()->create(['contract_id' => $otherContract->id]);

        $created = $this->runOn('2026-04-01')['created'];

        $this->assertCount(1, $created);
        $this->assertSame($this->company->id, $created->first()->company_id);
    }

    public function test_the_scheduled_command_makes_the_drafts(): void
    {
        $this->contract();

        $this->artisan('contracts:generate-drafts', ['--on' => '2026-04-01'])
            ->expectsOutputToContain('Napravljeno nacrta: 1.')
            ->assertSuccessful();

        $this->assertSame(1, Invoice::acrossCompanies()->drafts()->count());
    }

    public function test_a_dry_run_shows_the_work_without_doing_it(): void
    {
        $this->contract();

        $this->artisan('contracts:generate-drafts', ['--on' => '2026-04-01', '--dry-run' => true])
            ->expectsOutputToContain('Bilo bi napravljeno nacrta: 1.')
            ->assertSuccessful();

        $this->assertSame(0, Invoice::acrossCompanies()->count());
    }

    /**
     * The command runs with nobody logged in — the tenancy scope must not hide
     * every contract from it.
     */
    public function test_the_command_works_without_a_signed_in_user(): void
    {
        $this->contract();

        auth()->logout();
        app()->forgetInstance(CurrentCompany::class);

        $this->artisan('contracts:generate-drafts', ['--on' => '2026-04-01'])->assertSuccessful();

        $this->assertSame(1, Invoice::acrossCompanies()->drafts()->count());
    }

    /**
     * @return array{created: Collection, skipped: Collection}
     */
    private function runOn(string $date): array
    {
        return app(GenerateContractDrafts::class)->handle($this->company, Carbon::parse($date));
    }

    private function contract(?callable $customise = null): Contract
    {
        $factory = Contract::factory()->state([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'name' => 'Održavanje',
            'starts_on' => '2026-01-01',
            'payment_days' => 15,
        ]);

        $contract = ($customise ? $customise($factory) : $factory)->create();

        ContractItem::factory()->create([
            'contract_id' => $contract->id,
            'name' => 'Održavanje aplikacije',
            'quantity' => 1,
            'unit_price' => 50000,
            'vat_rate' => 20,
        ]);

        return $contract->refresh();
    }
}
