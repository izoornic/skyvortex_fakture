<?php

namespace Tests\Feature\Partners;

use App\Enums\PartnerType;
use App\Models\Company;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create(['pib' => '100000001']);
        $this->user = User::factory()->bookkeeper()->create();
        $this->user->companies()->attach($this->company);

        $this->actingAs($this->user);
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_bookkeeper_creates_a_legal_entity_partner(): void
    {
        Volt::test('partners.form')
            ->set('name', 'Kupac d.o.o.')
            ->set('pib', '200000002')
            ->set('registration_number', '12345678')
            ->set('city', 'Beograd')
            ->call('save')
            ->assertHasNoErrors();

        $partner = Partner::firstWhere('pib', '200000002');

        $this->assertNotNull($partner);
        $this->assertSame($this->company->id, $partner->company_id);
        $this->assertSame(PartnerType::LegalEntity, $partner->type);
    }

    public function test_legal_entity_requires_pib_and_registration_number(): void
    {
        Volt::test('partners.form')
            ->set('name', 'Bez identifikatora')
            ->call('save')
            ->assertHasErrors(['pib' => 'required', 'registration_number' => 'required']);
    }

    public function test_individual_does_not_require_a_tax_number(): void
    {
        Volt::test('partners.form')
            ->set('type', PartnerType::Individual->value)
            ->set('name', 'Petar Petrović')
            ->set('jmbg', '0101990710011')
            ->call('save')
            ->assertHasNoErrors();

        $partner = Partner::firstWhere('name', 'Petar Petrović');

        $this->assertNotNull($partner);
        $this->assertNull($partner->pib);
        $this->assertSame('0101990710011', $partner->jmbg);
    }

    public function test_jmbg_is_encrypted_at_rest(): void
    {
        $partner = Partner::factory()->individual()->create([
            'company_id' => $this->company->id,
            'jmbg' => '0101990710011',
        ]);

        $stored = DB::table('partners')->where('id', $partner->id)->value('jmbg');

        $this->assertNotSame('0101990710011', $stored);
        $this->assertSame('0101990710011', $partner->fresh()->jmbg);
    }

    public function test_jmbg_stays_out_of_the_audit_trail(): void
    {
        $partner = Partner::factory()->individual()->create([
            'company_id' => $this->company->id,
            'jmbg' => '0101990710011',
        ]);

        $audit = $partner->auditLogs()->first();

        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('jmbg', $audit->new_values);
    }

    public function test_the_issuer_cannot_be_its_own_partner(): void
    {
        Volt::test('partners.form')
            ->set('name', 'Sam sebi')
            ->set('pib', $this->company->pib)
            ->set('registration_number', '12345678')
            ->call('save')
            ->assertHasErrors('pib');
    }

    public function test_the_same_pib_is_allowed_under_a_different_issuer(): void
    {
        $other = Company::factory()->create(['pib' => '100000009']);

        Partner::factory()->create([
            'company_id' => $other->id,
            'pib' => '300000003',
        ]);

        Volt::test('partners.form')
            ->set('name', 'Isti kupac, drugi izdavalac')
            ->set('pib', '300000003')
            ->set('registration_number', '12345678')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Partner::acrossCompanies()->where('pib', '300000003')->count());
    }

    public function test_pib_is_unique_within_the_same_issuer(): void
    {
        Partner::factory()->create([
            'company_id' => $this->company->id,
            'pib' => '400000004',
        ]);

        Volt::test('partners.form')
            ->set('name', 'Duplikat')
            ->set('pib', '400000004')
            ->set('registration_number', '12345678')
            ->call('save')
            ->assertHasErrors(['pib' => 'unique']);
    }

    public function test_jmbg_is_rejected_for_anything_but_a_natural_person(): void
    {
        Volt::test('partners.form')
            ->set('type', PartnerType::LegalEntity->value)
            ->set('name', 'Firma sa JMBG-om')
            ->set('pib', '500000005')
            ->set('registration_number', '12345678')
            ->set('jmbg', '0101990710011')
            ->call('save')
            ->assertHasErrors('jmbg');
    }

    public function test_switching_the_type_clears_identifiers_that_no_longer_apply(): void
    {
        Volt::test('partners.form')
            ->set('pib', '600000006')
            ->set('registration_number', '12345678')
            ->set('type', PartnerType::Individual->value)
            ->assertSet('pib', '')
            ->assertSet('registration_number', '');
    }

    public function test_partners_are_scoped_to_the_active_company(): void
    {
        $other = Company::factory()->create();

        Partner::factory()->count(2)->create(['company_id' => $this->company->id]);
        Partner::factory()->count(3)->create(['company_id' => $other->id]);

        $this->assertSame(2, Partner::count());
        $this->assertSame(5, Partner::acrossCompanies()->count());
    }

    public function test_the_list_shows_only_partners_of_the_active_company(): void
    {
        $other = Company::factory()->create();

        Partner::factory()->create(['company_id' => $this->company->id, 'name' => 'Moj kupac']);
        Partner::factory()->create(['company_id' => $other->id, 'name' => 'Tuđi kupac']);

        $this->get('/partners')
            ->assertOk()
            ->assertSee('Moj kupac')
            ->assertDontSee('Tuđi kupac');
    }

    public function test_a_bookkeeper_cannot_edit_a_partner_of_another_company(): void
    {
        $other = Company::factory()->create();
        $partner = Partner::factory()->create(['company_id' => $other->id]);

        $this->get("/partners/{$partner->id}/edit")->assertNotFound();
    }
}
