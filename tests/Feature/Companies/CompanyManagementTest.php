<?php

namespace Tests\Feature\Companies;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceNumberSequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_company_and_its_number_sequence(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('companies.form')
            ->set('name', 'SkyVortex d.o.o.')
            ->set('pib', '123456789')
            ->set('registration_number', '87654321')
            ->set('address', 'Bulevar 1')
            ->set('city', 'Novi Sad')
            ->set('last_invoice_number', 42)
            ->call('save')
            ->assertHasNoErrors();

        $company = Company::firstWhere('pib', '123456789');

        $this->assertNotNull($company);
        $this->assertSame('SkyVortex d.o.o.', $company->name);
        $this->assertTrue($company->in_vat_system);

        $sequence = InvoiceNumberSequence::acrossCompanies()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($sequence);
        $this->assertSame(DocumentType::Invoice, $sequence->type);
        $this->assertSame(now()->year, $sequence->year);
        $this->assertSame(42, $sequence->last_number);
    }

    public function test_numbering_continues_after_documents_issued_outside_the_application(): void
    {
        $company = Company::factory()->create();

        $sequence = InvoiceNumberSequence::acrossCompanies()->create([
            'company_id' => $company->id,
            'type' => DocumentType::Invoice,
            'year' => 2026,
            'last_number' => 7,
        ]);

        $this->assertSame('2026-0008', $sequence->previewNextNumber());
    }

    public function test_every_document_type_but_the_invoice_carries_a_prefix(): void
    {
        $company = Company::factory()->create();

        $expected = [
            DocumentType::Invoice->value => '2026-0001',
            DocumentType::AdvanceInvoice->value => 'AV-2026-0001',
            DocumentType::CreditNote->value => 'KO-2026-0001',
            DocumentType::DebitNote->value => 'KZ-2026-0001',
            DocumentType::Proforma->value => 'PR-2026-0001',
        ];

        foreach (DocumentType::cases() as $type) {
            $sequence = InvoiceNumberSequence::acrossCompanies()->create([
                'company_id' => $company->id,
                'type' => $type,
                'year' => 2026,
                'last_number' => 0,
            ]);

            $this->assertSame($expected[$type->value], $sequence->previewNextNumber());
        }
    }

    public function test_pib_must_be_unique_and_nine_digits(): void
    {
        Company::factory()->create(['pib' => '111111111']);

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('companies.form')
            ->set('name', 'Druga firma')
            ->set('pib', '111111111')
            ->set('registration_number', '87654321')
            ->set('address', 'Bulevar 1')
            ->set('city', 'Novi Sad')
            ->set('last_invoice_number', 0)
            ->call('save')
            ->assertHasErrors(['pib' => 'unique']);

        Volt::test('companies.form')
            ->set('pib', '12345')
            ->call('save')
            ->assertHasErrors(['pib' => 'digits']);
    }

    public function test_admin_updates_a_company_without_touching_its_sequence(): void
    {
        $company = Company::factory()->create(['city' => 'Beograd']);

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('companies.form', ['company' => $company])
            ->set('city', 'Niš')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Niš', $company->fresh()->city);
        $this->assertSame(0, InvoiceNumberSequence::acrossCompanies()->count());
    }
}
