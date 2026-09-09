<?php

namespace Tests\Feature\Companies;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A stambena zajednica is a legal person with a PIB and a matični broj, but it
 * is not a company — the application has to be able to say which is which.
 */
class CompanyTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_company_is_an_ordinary_legal_entity_unless_told_otherwise(): void
    {
        $company = Company::factory()->create();

        $this->assertSame(CompanyType::LegalEntity, $company->type);
    }

    public function test_a_housing_community_can_be_entered_as_an_issuer(): void
    {
        Volt::test('companies.form')
            ->set('type', CompanyType::HousingCommunity->value)
            ->set('name', 'Stambena zajednica Cara Dušana 15')
            ->set('pib', '123456789')
            ->set('registration_number', '12345678')
            ->set('address', 'Cara Dušana 15')
            ->set('city', 'Pančevo')
            ->set('last_invoice_number', 0)
            ->call('save')
            ->assertHasNoErrors();

        $company = Company::where('pib', '123456789')->sole();

        $this->assertSame(CompanyType::HousingCommunity, $company->type);
        $this->assertSame('Stambena zajednica Cara Dušana 15', $company->name);
    }

    /**
     * The two fields disappear from the form, so whatever was typed into them
     * must go as well — otherwise it is saved where nobody can see it.
     */
    public function test_switching_to_a_housing_community_drops_the_fields_that_do_not_apply(): void
    {
        $component = Volt::test('companies.form')
            ->set('activity_code', '6201')
            ->set('jbkjs', '12345')
            ->set('type', CompanyType::HousingCommunity->value);

        $this->assertSame('', $component->get('activity_code'));
        $this->assertSame('', $component->get('jbkjs'));

        $component->assertDontSee('Šifra delatnosti')->assertDontSee('JBKJS');
    }

    public function test_an_ordinary_company_keeps_both_fields(): void
    {
        Volt::test('companies.form')
            ->set('type', CompanyType::LegalEntity->value)
            ->assertSee('Šifra delatnosti')
            ->assertSee('JBKJS');
    }

    public function test_editing_keeps_the_type_it_was_saved_with(): void
    {
        $company = Company::factory()->housingCommunity()->create();

        $component = Volt::test('companies.form', ['company' => $company]);

        $this->assertSame(CompanyType::HousingCommunity->value, $component->get('type'));
    }

    public function test_the_list_marks_anything_that_is_not_a_plain_company(): void
    {
        Company::factory()->create(['name' => 'Obična firma d.o.o.', 'short_name' => 'Obična']);
        Company::factory()->housingCommunity()->create(['name' => 'SZ Cara Dušana 15']);

        $html = Volt::test('companies.index')->html();

        $this->assertStringContainsString('Stambena zajednica', $html);

        // The badge marks the exception, not every row.
        $this->assertSame(1, substr_count($html, 'Stambena zajednica'));
    }

    public function test_an_unknown_type_is_refused(): void
    {
        Volt::test('companies.form')
            ->set('type', 'zadruga')
            ->set('name', 'Nešto')
            ->set('pib', '123456789')
            ->set('registration_number', '12345678')
            ->set('address', 'Adresa 1')
            ->set('city', 'Grad')
            ->call('save')
            ->assertHasErrors('type');
    }
}
