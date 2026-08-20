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
use Tests\TestCase;

class ImportPartnersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create(['pib' => '100000001']);

        $user = User::factory()->bookkeeper()->create();
        $user->companies()->attach($this->company);

        $this->actingAs($user);
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_it_imports_valid_rows_and_reports_the_rest(): void
    {
        $path = $this->csv(<<<'CSV'
        naziv;pib;maticni_broj;mesto;u_sistemu_pdv
        Prvi kupac d.o.o.;200000002;12345678;Beograd;da
        Drugi kupac d.o.o.;300000003;87654321;Novi Sad;ne
        Neispravan PIB;123;87654321;Niš;ne
        CSV);

        $result = app(ImportPartners::class)->handle($path, $this->company);

        $this->assertSame(3, $result['parsed']);
        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('Neispravan PIB', $result['errors'][0]['name']);

        $first = Partner::firstWhere('pib', '200000002');

        $this->assertTrue($first->in_vat_system);
        $this->assertSame('Beograd', $first->city);
        $this->assertFalse(Partner::firstWhere('pib', '300000003')->in_vat_system);
    }

    public function test_it_accepts_a_comma_separated_file(): void
    {
        $path = $this->csv(<<<'CSV'
        naziv,pib,maticni_broj
        Zarezom razdvojen,200000002,12345678
        CSV);

        $result = app(ImportPartners::class)->handle($path, $this->company);

        $this->assertSame(1, $result['created']);
    }

    public function test_it_updates_an_existing_partner_matched_by_pib(): void
    {
        Partner::factory()->create([
            'company_id' => $this->company->id,
            'pib' => '200000002',
            'city' => 'Beograd',
        ]);

        $path = $this->csv(<<<'CSV'
        naziv;pib;maticni_broj;mesto
        Preimenovani kupac;200000002;12345678;Kragujevac
        CSV);

        $result = app(ImportPartners::class)->handle($path, $this->company);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame('Kragujevac', Partner::firstWhere('pib', '200000002')->city);
    }

    public function test_existing_partners_are_left_alone_when_updating_is_off(): void
    {
        Partner::factory()->create([
            'company_id' => $this->company->id,
            'pib' => '200000002',
            'city' => 'Beograd',
        ]);

        $path = $this->csv(<<<'CSV'
        naziv;pib;maticni_broj;mesto
        Preimenovani kupac;200000002;12345678;Kragujevac
        CSV);

        $result = app(ImportPartners::class)->handle($path, $this->company, updateExisting: false);

        $this->assertSame(0, $result['updated']);
        $this->assertSame('Beograd', Partner::firstWhere('pib', '200000002')->city);
    }

    public function test_it_recognises_the_partner_type_from_the_file(): void
    {
        $path = $this->csv(<<<'CSV'
        tip;naziv;jmbg
        fizicko_lice;Petar Petrović;0101990710011
        CSV);

        $result = app(ImportPartners::class)->handle($path, $this->company);

        $this->assertSame(1, $result['created']);

        $partner = Partner::firstWhere('name', 'Petar Petrović');

        $this->assertSame(PartnerType::Individual, $partner->type);
        $this->assertSame('0101990710011', $partner->jmbg);
    }

    public function test_it_refuses_a_row_carrying_the_issuers_own_pib(): void
    {
        $path = $this->csv(<<<'CSV'
        naziv;pib;maticni_broj
        Sam sebi;100000001;12345678
        CSV);

        $result = app(ImportPartners::class)->handle($path, $this->company);

        $this->assertSame(0, $result['created']);
        $this->assertStringContainsString('partner samo sebi', mb_strtolower($result['errors'][0]['message']));
    }

    public function test_a_missing_currency_falls_back_to_the_company_default(): void
    {
        $path = $this->csv(<<<'CSV'
        naziv;pib;maticni_broj
        Bez valute;200000002;12345678
        CSV);

        app(ImportPartners::class)->handle($path, $this->company);

        $this->assertSame(
            $this->company->default_currency,
            Partner::firstWhere('pib', '200000002')->default_currency
        );
    }

    public function test_the_import_screen_reports_the_outcome(): void
    {
        $this->get('/partners/import')->assertOk()->assertSee('Očekivane kolone');
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'partners').'.csv';

        // Heredocs in the tests are indented; the file must not be.
        $lines = array_map('trim', explode("\n", trim($contents)));

        file_put_contents($path, implode("\n", $lines));

        return $path;
    }
}
