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
 * Every rendered <option> list must say which option is selected.
 *
 * Livewire re-renders the whole form on any round trip — picking a partner,
 * adding an item, changing the currency. The morph replaces the option markup,
 * and markup without a `selected` attribute means the browser falls back: to
 * the placeholder where one exists, otherwise to the first option. The server
 * keeps the real value, the screen shows another, and the deferred sync on
 * submit sends what the screen shows.
 *
 * That is how a chosen partner came back as "polje partner id je obavezno",
 * and how a 10% VAT line could have been saved as 20%.
 */
class InvoiceFormSelectStateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create();
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        $this->partner = Partner::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_the_chosen_partner_stays_chosen_after_a_re_render(): void
    {
        $component = Volt::test('invoices.form')->set('partner_id', $this->partner->id);

        $this->assertSame(
            (string) $this->partner->id,
            $this->selectedValue($component->html(), 'partner_id'),
        );
    }

    public function test_the_partner_placeholder_is_selected_only_while_nothing_is_chosen(): void
    {
        $component = Volt::test('invoices.form');

        $this->assertSame('', $this->selectedValue($component->html(), 'partner_id'));
    }

    public function test_adding_an_item_does_not_drop_the_chosen_partner(): void
    {
        $component = Volt::test('invoices.form')
            ->set('partner_id', $this->partner->id)
            ->call('addItem');

        $this->assertSame(
            (string) $this->partner->id,
            $this->selectedValue($component->html(), 'partner_id'),
        );
    }

    public function test_currency_and_item_selects_keep_their_value(): void
    {
        $component = Volt::test('invoices.form')
            ->set('currency', 'EUR')
            ->set('items.0.unit_code', 'MON')
            ->set('items.0.vat_rate', '10')
            ->set('items.0.vat_category', 'AE');

        $html = $component->html();

        $this->assertSame('EUR', $this->selectedValue($html, 'currency'));
        $this->assertSame('MON', $this->selectedValue($html, 'items.0.unit_code'));
        $this->assertSame('10', $this->selectedValue($html, 'items.0.vat_rate'));
        $this->assertSame('AE', $this->selectedValue($html, 'items.0.vat_category'));
    }

    public function test_a_ten_percent_line_is_still_ten_percent_after_a_re_render(): void
    {
        $component = Volt::test('invoices.form')
            ->set('partner_id', $this->partner->id)
            ->set('items.0.name', 'Licenca')
            ->set('items.0.unit_price', '1000')
            ->set('items.0.vat_rate', '10')
            ->call('addItem');

        // The screen must not quietly offer 20% back to the deferred sync.
        $this->assertSame('10', $this->selectedValue($component->html(), 'items.0.vat_rate'));
    }

    /**
     * Value of the option marked selected in the select bound to $field.
     */
    private function selectedValue(string $html, string $field): ?string
    {
        $position = strpos($html, 'wire:model.live="'.$field.'"');

        if ($position === false) {
            $position = strpos($html, 'wire:model="'.$field.'"');
        }

        $this->assertNotFalse($position, "Select za `{$field}` nije pronađen.");

        $chunk = substr($html, $position, 4000);
        $chunk = substr($chunk, 0, strpos($chunk, '</select>') ?: null);

        preg_match_all('/<option[^>]*>/', $chunk, $options);

        foreach ($options[0] as $option) {
            if (! str_contains($option, 'selected')) {
                continue;
            }

            preg_match('/value="([^"]*)"/', $option, $value);

            return $value[1] ?? null;
        }

        return null;
    }
}
