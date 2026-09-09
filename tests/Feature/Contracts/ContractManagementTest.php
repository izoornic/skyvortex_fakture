<?php

namespace Tests\Feature\Contracts;

use App\Enums\BillingMode;
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

class ContractManagementTest extends TestCase
{
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
            'payment_days' => 30,
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

    public function test_a_contract_is_created_with_its_lines(): void
    {
        Volt::test('contracts.form')
            ->set('partner_id', $this->partner->id)
            ->set('name', 'Održavanje aplikacije')
            ->set('billing_mode', BillingMode::Arrears->value)
            ->set('generation_day', 5)
            ->set('starts_on', '2026-01-01')
            ->set('items.0.name', 'Mesečno održavanje')
            ->set('items.0.unit_price', '75000')
            ->call('save')
            ->assertHasNoErrors();

        $contract = Contract::acrossCompanies()->sole();

        $this->assertSame('Održavanje aplikacije', $contract->name);
        $this->assertSame($this->company->id, $contract->company_id);
        $this->assertSame(5, $contract->generation_day);
        $this->assertSame(BillingMode::Arrears, $contract->billing_mode);
        $this->assertCount(1, $contract->items);
        $this->assertSame('75000.0000', $contract->items->first()->unit_price);
    }

    public function test_a_contract_can_be_billed_two_months_in_arrears(): void
    {
        Volt::test('contracts.form')
            ->set('partner_id', $this->partner->id)
            ->set('name', 'Održavanje sa kašnjenjem')
            ->set('billing_mode', BillingMode::ArrearsTwoMonths->value)
            ->set('starts_on', '2026-01-01')
            ->set('items.0.name', 'Mesečno održavanje')
            ->set('items.0.unit_price', '75000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(BillingMode::ArrearsTwoMonths, Contract::acrossCompanies()->sole()->billing_mode);
    }

    public function test_picking_a_partner_carries_over_its_terms(): void
    {
        $component = Volt::test('contracts.form')->set('partner_id', $this->partner->id);

        $this->assertSame(30, $component->get('payment_days'));
    }

    public function test_a_contract_without_lines_is_refused(): void
    {
        Volt::test('contracts.form')
            ->set('partner_id', $this->partner->id)
            ->set('name', 'Bez stavki')
            ->set('starts_on', '2026-01-01')
            ->call('removeItem', 0)
            ->call('save')
            ->assertHasErrors('items');

        $this->assertSame(0, Contract::acrossCompanies()->count());
    }

    public function test_an_end_date_before_the_start_is_refused(): void
    {
        Volt::test('contracts.form')
            ->set('partner_id', $this->partner->id)
            ->set('name', 'Naopako')
            ->set('starts_on', '2026-06-01')
            ->set('ends_on', '2026-01-01')
            ->set('items.0.name', 'Nešto')
            ->set('items.0.unit_price', '1000')
            ->call('save')
            ->assertHasErrors('ends_on');
    }

    public function test_editing_replaces_the_lines(): void
    {
        $contract = $this->contract();

        Volt::test('contracts.form', ['contract' => $contract])
            ->set('items.0.name', 'Novi naziv')
            ->set('items.0.unit_price', '99000')
            ->call('save')
            ->assertHasNoErrors();

        $contract->refresh();

        $this->assertCount(1, $contract->items);
        $this->assertSame('Novi naziv', $contract->items->first()->name);
    }

    public function test_the_list_shows_the_next_run_and_the_monthly_value(): void
    {
        $this->contract();

        Volt::test('contracts.index')
            ->assertSee('Održavanje')
            ->assertSee($this->partner->name)
            ->assertSee('60.000,00');
    }

    public function test_the_list_shows_the_total_of_all_contracts_beside_the_page_total(): void
    {
        config(['global.paginate' => 1]);

        $this->contract();

        $hosting = Contract::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'name' => 'Hosting',
            'starts_on' => '2026-01-01',
        ]);

        ContractItem::factory()->create([
            'contract_id' => $hosting->id,
            'unit_price' => 10000,
            'vat_rate' => 20,
        ]);

        Volt::test('contracts.index')
            ->assertViewHas('monthlyTotal', 12000.0)
            ->assertViewHas('monthlyTotalAll', 72000.0)
            ->assertSee('Mesečno na ovoj strani:')
            ->assertSee('Ukupno svi ugovori:');
    }

    public function test_the_total_of_all_contracts_follows_the_search(): void
    {
        $this->contract();

        $hosting = Contract::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'name' => 'Hosting',
            'starts_on' => '2026-01-01',
        ]);

        ContractItem::factory()->create([
            'contract_id' => $hosting->id,
            'unit_price' => 10000,
            'vat_rate' => 20,
        ]);

        Volt::test('contracts.index')
            ->set('search', 'Hosting')
            ->assertViewHas('monthlyTotalAll', 12000.0);
    }

    public function test_a_contract_can_be_switched_off_and_on(): void
    {
        $contract = $this->contract();

        Volt::test('contracts.index')->call('toggleActive', $contract->id);
        $this->assertFalse($contract->refresh()->is_active);

        Volt::test('contracts.index')->call('toggleActive', $contract->id);
        $this->assertTrue($contract->refresh()->is_active);
    }

    public function test_pausing_a_contract_reports_it_and_keeps_the_row_on_screen(): void
    {
        $contract = $this->contract();

        $component = Volt::test('contracts.index')
            ->call('toggleActive', $contract->id)
            ->assertSet('includeInactive', true)
            ->assertSee('je pauziran')
            ->assertSee('prikaz neaktivnih')
            ->assertSee($contract->name);

        $this->assertFalse($contract->refresh()->is_active);

        $component->call('toggleActive', $contract->id)
            ->assertSee('je ponovo aktivan');

        $this->assertTrue($contract->refresh()->is_active);
    }

    public function test_a_draft_can_be_made_from_the_list_without_waiting_for_the_run(): void
    {
        $contract = $this->contract();

        Volt::test('contracts.index')
            ->call('generateNow', $contract->id)
            ->assertRedirect();

        $invoice = Invoice::acrossCompanies()->sole();

        $this->assertSame($contract->id, $invoice->contract_id);
        $this->assertSame(3, $invoice->period_month);
    }

    /**
     * A contract that explains existing documents is closed, never deleted.
     */
    public function test_a_contract_with_documents_cannot_be_deleted(): void
    {
        $contract = $this->contract();

        Invoice::factory()->forCompany($this->company)->create(['contract_id' => $contract->id]);

        Volt::test('contracts.index')->call('delete', $contract->id)->assertForbidden();

        $this->assertSame(1, Contract::acrossCompanies()->count());
    }

    public function test_an_unused_contract_can_be_deleted(): void
    {
        $contract = $this->contract();

        Volt::test('contracts.index')->call('delete', $contract->id);

        $this->assertSame(0, Contract::acrossCompanies()->count());
    }

    public function test_contracts_of_another_company_are_not_visible(): void
    {
        $this->contract();

        $other = Company::factory()->create();
        $otherPartner = Partner::factory()->create(['company_id' => $other->id]);
        Contract::factory()->create([
            'company_id' => $other->id,
            'partner_id' => $otherPartner->id,
            'name' => 'Tudji ugovor',
        ]);

        Volt::test('contracts.index')
            ->assertSee('Održavanje')
            ->assertDontSee('Tudji ugovor');
    }

    public function test_a_bookkeeper_without_the_company_cannot_open_the_contract(): void
    {
        $contract = $this->contract();

        $bookkeeper = User::factory()->bookkeeper()->create();

        $this->actingAs($bookkeeper)
            ->get("/ugovori/{$contract->id}/izmena")
            ->assertForbidden();
    }

    private function contract(): Contract
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $this->partner->id,
            'name' => 'Održavanje',
            'starts_on' => '2026-01-01',
        ]);

        ContractItem::factory()->create([
            'contract_id' => $contract->id,
            'unit_price' => 50000,
            'vat_rate' => 20,
        ]);

        return $contract->refresh();
    }
}
