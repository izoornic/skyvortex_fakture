<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanyLogoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Company::LOGO_DISK);

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->admin()->create();

        $this->actingAs($this->admin);
    }

    public function test_an_administrator_puts_a_logo_on_a_company(): void
    {
        Volt::test('companies.form', ['company' => $this->company])
            ->set('logo', $this->svgFile())
            ->call('save')
            ->assertHasNoErrors();

        $this->company->refresh();

        $this->assertNotNull($this->company->logo_path);
        $this->assertStringEndsWith('.svg', $this->company->logo_path);
        $this->assertTrue($this->company->hasLogo());

        Storage::disk(Company::LOGO_DISK)->assertExists($this->company->logo_path);
    }

    /**
     * The file on disk is the cleaned one — nothing is left to be filtered on
     * the way out.
     */
    public function test_what_lands_on_disk_is_sanitized(): void
    {
        $dangerous = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
            .'<script>alert(document.cookie)</script><rect width="10" height="10" fill="#0f766e"/></svg>';

        Volt::test('companies.form', ['company' => $this->company])
            ->set('logo', UploadedFile::fake()->createWithContent('logo.svg', $dangerous))
            ->call('save')
            ->assertHasNoErrors();

        $stored = Storage::disk(Company::LOGO_DISK)->get($this->company->refresh()->logo_path);

        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringContainsString('#0f766e', $stored);
    }

    public function test_a_replaced_logo_does_not_leave_its_file_behind(): void
    {
        $this->storeLogo();
        $first = $this->company->refresh()->logo_path;

        Volt::test('companies.form', ['company' => $this->company->fresh()])
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 80))
            ->call('save')
            ->assertHasNoErrors();

        $second = $this->company->refresh()->logo_path;

        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('.png', $second);

        Storage::disk(Company::LOGO_DISK)->assertMissing($first);
        Storage::disk(Company::LOGO_DISK)->assertExists($second);
    }

    public function test_removing_the_logo_clears_the_column_and_the_file(): void
    {
        $this->storeLogo();
        $path = $this->company->refresh()->logo_path;

        Volt::test('companies.form', ['company' => $this->company->fresh()])
            ->call('removeLogo')
            ->assertHasNoErrors();

        $this->assertNull($this->company->refresh()->logo_path);
        $this->assertFalse($this->company->hasLogo());

        Storage::disk(Company::LOGO_DISK)->assertMissing($path);
    }

    /**
     * The extension says SVG, the content is something else. The extension is
     * the client's word; the content is the evidence.
     */
    public function test_a_file_that_only_pretends_to_be_an_svg_is_refused(): void
    {
        Volt::test('companies.form', ['company' => $this->company])
            ->set('logo', UploadedFile::fake()->createWithContent('logo.svg', '<html><body>zdravo</body></html>'))
            ->call('save')
            ->assertHasErrors('logo');

        $this->assertNull($this->company->refresh()->logo_path);
    }

    public function test_other_file_types_are_refused(): void
    {
        Volt::test('companies.form', ['company' => $this->company])
            ->set('logo', UploadedFile::fake()->createWithContent('logo.php', 'echo 1;'))
            ->call('save')
            ->assertHasErrors('logo');

        $this->assertNull($this->company->refresh()->logo_path);
    }

    public function test_a_new_company_can_be_created_with_its_logo_at_once(): void
    {
        $this->newCompanyForm()
            ->set('logo', $this->svgFile())
            ->call('save')
            ->assertHasNoErrors();

        $company = Company::where('pib', '123456789')->sole();

        $this->assertNotNull($company->logo_path);
        Storage::disk(Company::LOGO_DISK)->assertExists($company->logo_path);
    }

    /**
     * The logo is stored inside the same transaction that creates the company,
     * so a refused file cannot leave a company and a number sequence behind.
     */
    public function test_a_refused_logo_leaves_no_half_created_company(): void
    {
        $this->newCompanyForm()
            ->set('logo', UploadedFile::fake()->createWithContent('logo.svg', '<html><body>ne</body></html>'))
            ->call('save')
            ->assertHasErrors('logo');

        $this->assertDatabaseMissing('companies', ['pib' => '123456789']);
        $this->assertDatabaseMissing('invoice_number_sequences', ['year' => now()->year, 'last_number' => 7]);
    }

    public function test_a_bookkeeper_never_reaches_the_logo(): void
    {
        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($this->company);

        $this->actingAs($bookkeeper)
            ->get("/companies/{$this->company->id}/edit")
            ->assertForbidden();
    }

    public function test_the_logo_is_served_to_whoever_may_see_the_company(): void
    {
        $this->storeLogo();

        $response = $this->actingAs($this->admin)->get($this->company->refresh()->logoUrl());

        $response->assertOk();
        $response->assertHeader('content-type', 'image/svg+xml');
        $response->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString(
            "default-src 'none'",
            (string) $response->headers->get('content-security-policy'),
        );
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_a_company_without_a_logo_has_nothing_to_serve(): void
    {
        $this->actingAs($this->admin)
            ->get(route('companies.logo', $this->company))
            ->assertNotFound();
    }

    public function test_a_user_outside_the_company_gets_nothing(): void
    {
        $this->storeLogo();

        $outsider = User::factory()->bookkeeper()->create();

        $this->actingAs($outsider)
            ->get(route('companies.logo', $this->company))
            ->assertForbidden();
    }

    /**
     * The address changes with the file, so a replaced logo is never taken from
     * the browser cache.
     */
    public function test_the_address_of_the_logo_follows_the_file(): void
    {
        $this->storeLogo();
        $first = $this->company->refresh()->logoUrl();

        Volt::test('companies.form', ['company' => $this->company->fresh()])
            ->set('logo', UploadedFile::fake()->image('drugi.png', 100, 40))
            ->call('save');

        $this->assertNotSame($first, $this->company->refresh()->logoUrl());
    }

    /**
     * The create form, filled in with everything but the logo.
     */
    private function newCompanyForm(): Testable
    {
        return Volt::test('companies.form')
            ->set('name', 'Nova firma d.o.o.')
            ->set('pib', '123456789')
            ->set('registration_number', '12345678')
            ->set('address', 'Cara Dušana 1')
            ->set('city', 'Pančevo')
            ->set('last_invoice_number', 7);
    }

    private function storeLogo(): void
    {
        Volt::test('companies.form', ['company' => $this->company])
            ->set('logo', $this->svgFile())
            ->call('save')
            ->assertHasNoErrors();
    }

    private function svgFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40" width="120" height="40">'
            .'<rect width="120" height="40" fill="#0f766e"/></svg>',
        );
    }
}
