<?php

namespace Tests\Feature\Partners;

use App\Actions\ImportPartners;
use App\Enums\PartnerType;
use App\Models\Company;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A stambena zajednica is registered like any other legal person: it carries a
 * PIB and a matični broj, and never a JMBG.
 */
class HousingCommunityPartnerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create(['pib' => '111111111']);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_the_type_is_offered_on_the_form(): void
    {
        Volt::test('partners.form')->assertSee('Stambena zajednica');
    }

    public function test_a_housing_community_is_entered_with_both_registry_numbers(): void
    {
        Volt::test('partners.form')
            ->set('type', PartnerType::HousingCommunity->value)
            ->set('name', 'Stambena zajednica Cara Dušana 15')
            ->set('pib', '222222222')
            ->set('registration_number', '87654321')
            ->set('address', 'Cara Dušana 15')
            ->set('city', 'Pančevo')
            ->call('save')
            ->assertHasNoErrors();

        $partner = Partner::sole();

        $this->assertSame(PartnerType::HousingCommunity, $partner->type);
        $this->assertSame('222222222', $partner->pib);
        $this->assertSame('87654321', $partner->registration_number);
    }

    public function test_it_is_refused_without_a_tax_number(): void
    {
        Volt::test('partners.form')
            ->set('type', PartnerType::HousingCommunity->value)
            ->set('name', 'Stambena zajednica bez PIB-a')
            ->set('city', 'Pančevo')
            ->call('save')
            ->assertHasErrors(['pib', 'registration_number']);

        $this->assertSame(0, Partner::count());
    }

    public function test_it_carries_no_personal_number(): void
    {
        Volt::test('partners.form')
            ->set('type', PartnerType::HousingCommunity->value)
            ->set('name', 'Stambena zajednica Cara Dušana 15')
            ->set('pib', '222222222')
            ->set('registration_number', '87654321')
            ->set('jmbg', '0101990710012')
            ->call('save')
            ->assertHasErrors('jmbg');
    }

    public function test_the_identifier_shown_next_to_the_name_is_the_pib(): void
    {
        $partner = Partner::factory()->create([
            'company_id' => $this->company->id,
            'type' => PartnerType::HousingCommunity,
            'pib' => '222222222',
        ]);

        $this->assertSame('222222222', $partner->identifier());
    }

    /**
     * The CSV column carries the label as people write it, with a space.
     */
    public function test_the_import_recognises_it_from_a_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'partneri').'.csv';

        file_put_contents($path, implode("\n", [
            'tip;naziv;pib;maticni_broj;mesto',
            'stambena zajednica;SZ Cara Dušana 15;222222222;87654321;Pančevo',
        ]));

        $result = app(ImportPartners::class)->handle($path, $this->company);

        unlink($path);

        $this->assertSame(1, $result['created'], implode(' ', array_column($result['errors'], 'message')));
        $this->assertSame(PartnerType::HousingCommunity, Partner::sole()->type);
    }
}
