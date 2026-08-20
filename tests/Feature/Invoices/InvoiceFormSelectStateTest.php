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
     * A disabled option cannot hold the selection. The browser falls through to
     * the first option it may select, so the box showed a partner the server
     * had never been told about — and picking that same partner changed
     * nothing, fired no event, and sent nothing.
     */
    public function test_no_empty_option_is_disabled(): void
    {
        $html = Volt::test('invoices.form')->html();

        preg_match_all('/<option[^>]*value=""[^>]*>/', $html, $options);

        $this->assertNotEmpty($options[0]);

        foreach ($options[0] as $option) {
            $this->assertStringNotContainsString(
                'disabled',
                $option,
                'Prazna opcija je onemogućena, pa pregledač prikazuje prvu stvarnu: '.$option,
            );
        }
    }

    /**
     * What the browser would show has to match what the server holds. Anything
     * else means the user is looking at a value nobody recorded.
     */
    public function test_what_the_browser_shows_matches_the_server(): void
    {
        $component = Volt::test('invoices.form');

        $this->assertSame(
            '',
            $this->browserValue($component->html(), 'partner_id'),
            'Prazan formular ne sme da prikazuje partnera koji nije izabran.',
        );

        $component->set('partner_id', $this->partner->id);

        $this->assertSame(
            (string) $this->partner->id,
            $this->browserValue($component->html(), 'partner_id'),
        );
    }

    public function test_only_one_empty_option_per_select(): void
    {
        $html = Volt::test('invoices.form')->html();

        foreach (['partner_id', 'bank_account_id', 'vat_exemption_reason_id'] as $field) {
            $chunk = $this->selectMarkup($html, $field);

            if ($chunk === null) {
                continue;
            }

            preg_match_all('/<option[^>]*value=""[^>]*>/', $chunk, $options);

            $this->assertLessThanOrEqual(
                1,
                count($options[0]),
                "Select `{$field}` ima više praznih opcija.",
            );
        }
    }

    /**
     * The value a browser would end up showing: the selected option it is
     * allowed to select, otherwise the first one it is allowed to select.
     */
    private function browserValue(string $html, string $field): ?string
    {
        $chunk = $this->selectMarkup($html, $field);

        $this->assertNotNull($chunk, "Select za `{$field}` nije pronađen.");

        preg_match_all('/<option[^>]*>/', $chunk, $options);

        $firstSelectable = null;

        foreach ($options[0] as $option) {
            if (str_contains($option, 'disabled')) {
                continue;
            }

            preg_match('/value="([^"]*)"/', $option, $value);

            $firstSelectable ??= $value[1] ?? null;

            if (str_contains($option, 'selected')) {
                return $value[1] ?? null;
            }
        }

        return $firstSelectable;
    }

    private function selectMarkup(string $html, string $field): ?string
    {
        $position = strpos($html, 'wire:model.live="'.$field.'"');

        if ($position === false) {
            $position = strpos($html, 'wire:model="'.$field.'"');
        }

        if ($position === false) {
            return null;
        }

        $chunk = substr($html, $position, 6000);
        $end = strpos($chunk, '</select>');

        return $end === false ? $chunk : substr($chunk, 0, $end);
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
