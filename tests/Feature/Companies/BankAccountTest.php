<?php

namespace Tests\Feature\Companies;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_an_account_to_a_company(): void
    {
        $company = Company::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('companies.bank-accounts', ['company' => $company])
            ->call('add')
            ->set('bank_name', 'Banca Intesa')
            ->set('account_number', '160000000000000018')
            ->call('save')
            ->assertHasNoErrors();

        $account = BankAccount::acrossCompanies()->firstWhere('company_id', $company->id);

        $this->assertNotNull($account);
        $this->assertSame('Banca Intesa', $account->bank_name);
        // The first account of a company becomes the primary one.
        $this->assertTrue($account->is_primary);
    }

    public function test_marking_an_account_primary_demotes_the_previous_one(): void
    {
        $company = Company::factory()->create();

        $first = BankAccount::factory()->primary()->create(['company_id' => $company->id]);
        $second = BankAccount::factory()->create(['company_id' => $company->id]);

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('companies.bank-accounts', ['company' => $company])
            ->call('edit', $second->id)
            ->set('is_primary', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_account_number_is_unique_within_a_company_only(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'account_number' => '160000000000000018',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('companies.bank-accounts', ['company' => $company])
            ->call('add')
            ->set('bank_name', 'Druga banka')
            ->set('account_number', '160000000000000018')
            ->call('save')
            ->assertHasErrors(['account_number' => 'unique']);

        // The same number under another company is fine.
        Volt::test('companies.bank-accounts', ['company' => $otherCompany])
            ->call('add')
            ->set('bank_name', 'Druga banka')
            ->set('account_number', '160000000000000018')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_bookkeeper_cannot_add_an_account(): void
    {
        $company = Company::factory()->create();

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($company);

        $this->actingAs($bookkeeper);

        Volt::test('companies.bank-accounts', ['company' => $company])
            ->call('add')
            ->assertForbidden();
    }

    public function test_account_numbers_are_shown_in_the_serbian_format(): void
    {
        $account = new BankAccount(['account_number' => '160000000000000018']);

        $this->assertSame('160-0000000000000-18', $account->formattedAccountNumber());

        // Anything that is not 18 digits is left untouched.
        $short = new BankAccount(['account_number' => '12345']);

        $this->assertSame('12345', $short->formattedAccountNumber());
    }
}
