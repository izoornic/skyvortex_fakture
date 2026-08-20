<?php

namespace Tests\Feature\Codebooks;

use App\Enums\VatCategory;
use App\Models\Currency;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CodebookTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_fills_the_standard_codebooks(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame('Srpski dinar', Currency::firstWhere('code', 'RSD')->name);
        $this->assertSame('Komad', UnitOfMeasure::firstWhere('code', 'H87')->name);
        $this->assertTrue(VatRate::firstWhere('rate', 20.00)->is_default);
    }

    public function test_the_seeder_can_be_run_twice_without_duplicating(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $before = Currency::count();

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame($before, Currency::count());
    }

    public function test_exemption_reasons_are_deliberately_not_seeded(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        // The codes come from tax regulation, not from a guess in the seeder.
        $this->assertSame(0, VatExemptionReason::count());
    }

    public function test_bookkeeper_may_read_but_not_change_a_codebook(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->actingAs(User::factory()->bookkeeper()->create());

        $this->get('/sifarnici/valute')->assertOk()->assertSee('Srpski dinar');

        Volt::test('codebooks.currencies')
            ->assertSet('canManage', false)
            ->call('add')
            ->assertForbidden();
    }

    public function test_admin_adds_a_currency(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('codebooks.currencies')
            ->call('add')
            ->set('code', 'nok')
            ->set('name', 'Norveška kruna')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Norveška kruna', Currency::firstWhere('code', 'NOK')?->name);
    }

    public function test_only_one_vat_rate_stays_the_default(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->actingAs(User::factory()->admin()->create());

        $special = VatRate::firstWhere('rate', 10.00);

        Volt::test('codebooks.vat-rates')
            ->call('edit', $special->id)
            ->set('is_default', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, VatRate::where('is_default', true)->count());
        $this->assertTrue($special->fresh()->is_default);
    }

    public function test_a_vat_rate_cannot_end_before_it_starts(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('codebooks.vat-rates')
            ->call('add')
            ->set('name', 'Besmislena')
            ->set('rate', '20')
            ->set('valid_from', '2026-01-01')
            ->set('valid_to', '2025-01-01')
            ->call('save')
            ->assertHasErrors('valid_to');
    }

    public function test_rates_are_filtered_by_the_day_they_applied(): void
    {
        VatRate::create([
            'name' => 'Stara stopa',
            'rate' => 18.00,
            'valid_from' => '2010-01-01',
            'valid_to' => '2012-09-30',
        ]);

        VatRate::create([
            'name' => 'Važeća stopa',
            'rate' => 20.00,
            'valid_from' => '2012-10-01',
            'valid_to' => null,
        ]);

        $old = VatRate::query()->validOn(now()->parse('2011-06-01'))->pluck('name');
        $current = VatRate::query()->validOn(now())->pluck('name');

        $this->assertSame(['Stara stopa'], $old->all());
        $this->assertSame(['Važeća stopa'], $current->all());
    }

    public function test_admin_adds_an_exemption_reason(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('codebooks.vat-exemptions')
            ->call('add')
            ->set('code', 'PDV-TEST-1')
            ->set('vat_category', VatCategory::Exempt->value)
            ->set('description', 'Oslobođeno po testnom osnovu')
            ->call('save')
            ->assertHasNoErrors();

        $reason = VatExemptionReason::firstWhere('code', 'PDV-TEST-1');

        $this->assertNotNull($reason);
        $this->assertSame(VatCategory::Exempt, $reason->vat_category);
    }

    public function test_vat_categories_know_when_a_reason_is_required(): void
    {
        $this->assertFalse(VatCategory::Standard->requiresExemptionReason());
        $this->assertTrue(VatCategory::Exempt->requiresExemptionReason());
        $this->assertTrue(VatCategory::ReverseCharge->requiresExemptionReason());
    }
}
