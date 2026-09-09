<?php

namespace Tests\Feature\Invoices;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A `@foreach ($currencies as $currency)` loop variable outlives its loop in
 * Blade. It shadowed the component's `$currency` property for the rest of the
 * template, so every amount was labelled with the serialized last Currency row
 * instead of the code, and `$currency !== 'RSD'` was always true.
 */
class InvoiceFormCurrencyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create(['default_currency' => 'RSD']);
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        Partner::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_amounts_are_labelled_with_the_currency_code(): void
    {
        $html = Volt::test('invoices.form')->html();

        $this->assertStringNotContainsString(
            'decimal_places',
            $html,
            'Formular ispisuje ceo red iz tabele currencies umesto oznake valute.',
        );

        $this->assertStringContainsString('Vrednost stavke:', $html);
        $this->assertMatchesRegularExpression('/Vrednost stavke:.*?0,00\s*RSD/s', $html);
        $this->assertMatchesRegularExpression('/Osnovica.*?0,00\s*RSD/s', $html);
        $this->assertMatchesRegularExpression('/Za uplatu.*?0,00\s*RSD/s', $html);
    }

    public function test_the_exchange_rate_field_appears_only_for_a_foreign_currency(): void
    {
        $component = Volt::test('invoices.form');

        $this->assertStringNotContainsString('Kurs', $component->html());

        $component->set('currency', 'EUR');

        $this->assertStringContainsString('Kurs', $component->html());
        $this->assertMatchesRegularExpression('/Za uplatu.*?0,00\s*EUR/s', $component->html());
    }
}
