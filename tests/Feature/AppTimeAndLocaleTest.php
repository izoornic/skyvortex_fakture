<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Vreme i jezik aplikacije ne smeju zavisiti od servera: shared hosting radi
 * u UTC-u, a `.env` na njemu se podešava ručno i ključ ume da izostane.
 */
class AppTimeAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create();
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        Partner::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_application_runs_in_belgrade_time_and_serbian_locale(): void
    {
        $this->assertSame('Europe/Belgrade', config('app.timezone'));
        $this->assertSame('Europe/Belgrade', now()->timezone->getName());
        $this->assertSame('sr', config('app.locale'));
    }

    /**
     * Prvih sat-dva svakog dana u UTC-u još pripada prethodnom datumu, pa bi
     * faktura otvorena u 00:30 dobila jučerašnji datum, a prvog u mesecu i
     * prethodni obračunski period.
     */
    public function test_new_invoice_defaults_to_the_serbian_calendar_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-28 23:30:00', 'UTC'));

        Volt::test('invoices.form')
            ->assertSet('issue_date', '2026-03-01')
            ->assertSet('supply_date', '2026-03-01')
            ->assertSet('period_year', 2026)
            ->assertSet('period_month', 3);
    }

    public function test_period_heading_is_serbian_even_when_the_locale_is_not_set(): void
    {
        app()->setLocale('en');

        Volt::test('invoices.index')
            ->set('year', 2026)
            ->set('month', 9)
            ->assertSee('septembar 2026.');
    }
}
