<?php

namespace Tests\Feature\Contracts;

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
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * "Gotovo kada: mesečno fakturisanje se svodi na pregled i potvrdu." This is
 * that screen, driven the way a person drives it.
 */
class MonthlyRunTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        Carbon::setTestNow('2026-04-01 08:00:00');

        $this->company = Company::factory()->create();
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_screen_lists_what_the_contracts_owe_before_anything_is_made(): void
    {
        $contract = $this->contract('Održavanje sajta');

        Volt::test('invoices.monthly-run')
            ->assertSee('Održavanje sajta')
            ->assertSee($contract->partner->name)
            ->assertSee('mart 2026.');

        $this->assertSame(0, Invoice::acrossCompanies()->count());
    }

    public function test_the_whole_month_goes_out_in_two_presses(): void
    {
        $this->contract('Prvi');
        $this->contract('Drugi');

        $component = Volt::test('invoices.monthly-run')->call('generate');

        $this->assertSame(2, Invoice::acrossCompanies()->drafts()->count());

        $drafts = Invoice::acrossCompanies()->drafts()->pluck('id')->all();

        $component->set('selected', $drafts)->call('issueSelected');

        $issued = Invoice::acrossCompanies()->get();

        $this->assertCount(2, $issued);
        $this->assertTrue($issued->every(fn (Invoice $invoice) => $invoice->status === InvoiceStatus::Issued));
        $this->assertTrue($issued->every(fn (Invoice $invoice) => $invoice->number !== null));
        $this->assertSame(['2026-0001', '2026-0002'], $issued->pluck('number')->sort()->values()->all());
    }

    public function test_selecting_everything_selects_every_draft(): void
    {
        $this->contract('Prvi');
        $this->contract('Drugi');

        $component = Volt::test('invoices.monthly-run')
            ->call('generate')
            ->set('selectAll', true);

        $this->assertCount(2, $component->get('selected'));
    }

    /**
     * One bad document must not hold back the rest of the month.
     */
    public function test_a_draft_that_cannot_be_issued_does_not_stop_the_others(): void
    {
        $this->contract('Ispravan');

        $broken = Invoice::factory()->forCompany($this->company)->create();

        Volt::test('invoices.monthly-run')
            ->call('generate')
            ->set('selected', Invoice::acrossCompanies()->drafts()->pluck('id')->all())
            ->call('issueSelected')
            ->assertSee('Nije izdato')
            ->assertSee('Dokument bez stavki ne može biti izdat.');

        $this->assertSame(InvoiceStatus::Draft, $broken->refresh()->status);
        $this->assertSame(1, Invoice::acrossCompanies()->issued()->count());
    }

    public function test_running_the_same_day_twice_does_not_double_the_month(): void
    {
        $this->contract('Održavanje');

        Volt::test('invoices.monthly-run')->call('generate')->call('generate');

        $this->assertSame(1, Invoice::acrossCompanies()->count());
    }

    public function test_a_draft_can_be_thrown_away_from_the_screen(): void
    {
        $this->contract('Održavanje');

        $component = Volt::test('invoices.monthly-run')->call('generate');

        $draft = Invoice::acrossCompanies()->drafts()->sole();

        $component->call('deleteDraft', $draft->id);

        $this->assertSame(0, Invoice::acrossCompanies()->count());
    }

    public function test_the_run_date_can_be_moved_back_to_catch_a_missed_day(): void
    {
        $this->contract('Održavanje', fn ($factory) => $factory->generatingOn(20));

        // On 1 April the 20th has not come round yet.
        Volt::test('invoices.monthly-run')
            ->call('generate')
            ->assertSet('generated.created', 0);

        Volt::test('invoices.monthly-run')
            ->set('runOn', '2026-03-25')
            ->call('generate')
            ->assertSet('generated.created', 1);

        $this->assertSame(2, Invoice::acrossCompanies()->drafts()->sole()->period_month);
    }

    private function contract(string $name, ?callable $customise = null): Contract
    {
        $partner = Partner::factory()->create(['company_id' => $this->company->id]);

        $factory = Contract::factory()->state([
            'company_id' => $this->company->id,
            'partner_id' => $partner->id,
            'name' => $name,
            'starts_on' => '2026-01-01',
        ]);

        $contract = ($customise ? $customise($factory) : $factory)->create();

        ContractItem::factory()->create([
            'contract_id' => $contract->id,
            'unit_price' => 40000,
            'vat_rate' => 20,
        ]);

        return $contract->refresh();
    }
}
