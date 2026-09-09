<?php

namespace Tests\Feature\Seeders;

use App\Enums\BillingMode;
use App\Enums\CompanyType;
use App\Enums\ContractFrequency;
use App\Enums\DocumentType;
use App\Enums\PartnerType;
use App\Enums\VatCategory;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Company::LOGO_DISK);
    }

    public function test_it_seeds_digital_skyvortex_with_its_registry_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->firstOrFail();

        $this->assertSame('Digital Skyvortex', $company->short_name);
        $this->assertSame(CompanyType::Entrepreneur, $company->type);
        $this->assertSame('67516184', $company->registration_number);
        $this->assertSame('Kragujevac', $company->city);
        $this->assertSame('+381 63 7265 275', $company->phone);
        $this->assertFalse($company->in_vat_system);
        $this->assertSame('PDV-RS-33', $company->vatExemptionReason->code);
        $this->assertSame('325-9500700213457-24', $company->primaryBankAccount->account_number);
    }

    public function test_it_puts_the_logo_where_the_application_serves_it_from(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->firstOrFail();

        $this->assertSame('logos/'.$company->getKey(), dirname((string) $company->logo_path));
        $this->assertTrue($company->hasLogo());
        $this->assertSame('image/svg+xml', $company->logoMimeType());
    }

    public function test_it_seeds_every_real_partner_of_digital_skyvortex(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->firstOrFail();

        $this->assertSame(78, $company->partners()->count());
        $this->assertSame(76, $company->partners()->where('type', PartnerType::HousingCommunity)->count());

        $partner = Partner::where('pib', '110498123')->firstOrFail();

        $this->assertSame($company->getKey(), $partner->company_id);
        $this->assertSame('SZ Kopaonička 1 V', $partner->name);
        $this->assertSame('18023253', $partner->registration_number);
        $this->assertSame(30, $partner->payment_days);
        $this->assertFalse($partner->in_vat_system);
    }

    public function test_it_seeds_the_managers_the_partners_are_delivered_to(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->firstOrFail();

        $this->assertSame(5, $company->partnerGroups()->count());

        $group = PartnerGroup::where('name', 'Aleksandar Milić')->firstOrFail();

        $this->assertSame('milic984kg@gmail.com', $group->email);
        $this->assertSame(10, $group->partners()->count());

        $partner = Partner::where('pib', '110498123')->firstOrFail();

        $this->assertSame('Aleksandar Filopvić', $partner->partnerGroup?->name);
    }

    public function test_it_seeds_every_standing_contract_with_its_line(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->firstOrFail();

        $this->assertSame(79, $company->contracts()->count());
        $this->assertSame(79, ContractItem::count());

        $contract = Contract::acrossCompanies()
            ->where('name', 'Potal fiskalnih terminala')
            ->firstOrFail();

        $this->assertSame('102054577', $contract->partner->pib);
        $this->assertSame(BillingMode::Arrears, $contract->billing_mode);
        $this->assertSame(ContractFrequency::Monthly, $contract->frequency);
        $this->assertSame(1, $contract->generation_day);
        $this->assertSame('2026-09-01', $contract->starts_on->toDateString());
        $this->assertSame(30, $contract->payment_days);
        $this->assertTrue($contract->is_active);
        $this->assertSame(
            $company->primaryBankAccount->getKey(),
            $contract->bank_account_id,
        );

        $item = $contract->items()->sole();

        $this->assertSame('46800.0000', $item->unit_price);
        $this->assertSame('MON', $item->unit_code);
        $this->assertSame('mes', $item->unit_symbol);
        $this->assertSame(VatCategory::OutOfScope, $item->vat_category);
        $this->assertSame('PDV-RS-33', $item->vatExemptionReason->code);
    }

    public function test_a_second_run_refreshes_a_contract_instead_of_doubling_it(): void
    {
        $this->seed(DatabaseSeeder::class);

        $contract = Contract::acrossCompanies()->where('name', 'Potal fiskalnih terminala')->firstOrFail();
        $contract->items()->update(['unit_price' => 1]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(79, Contract::acrossCompanies()->count());
        $this->assertSame(79, ContractItem::count());
        $this->assertSame('46800.0000', $contract->items()->sole()->unit_price);
    }

    public function test_it_continues_the_numbering_of_invoices_issued_elsewhere(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->firstOrFail();

        $sequence = $company->numberSequences()
            ->where('type', DocumentType::Invoice)
            ->where('year', 2026)
            ->firstOrFail();

        $this->assertSame('2026-0639', $sequence->previewNextNumber());
    }

    public function test_a_second_run_never_pushes_the_numbering_back(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->firstOrFail();
        $company->numberSequences()->where('year', 2026)->update(['last_number' => 640]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(640, $company->numberSequences()->where('year', 2026)->firstOrFail()->last_number);
    }

    public function test_it_leaves_the_installation_without_documents(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Invoice::count());
        $this->assertSame(2, User::count());
        $this->assertSame(1, User::where('email', 'knjigovodja@skyvortex.test')->firstOrFail()->companies()->count());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $companies = Company::count();
        $partners = Partner::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($companies, Company::count());
        $this->assertSame($partners, Partner::count());
        $this->assertSame(2, User::count());
    }
}
