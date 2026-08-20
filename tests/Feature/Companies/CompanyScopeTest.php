<?php

namespace Tests\Feature\Companies;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_owned_records_are_limited_to_the_active_company(): void
    {
        $mine = Company::factory()->create();
        $theirs = Company::factory()->create();

        BankAccount::factory()->count(2)->create(['company_id' => $mine->id]);
        BankAccount::factory()->count(3)->create(['company_id' => $theirs->id]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        app(CurrentCompany::class)->set($mine);

        $this->assertSame(2, BankAccount::count());
        $this->assertSame(5, BankAccount::acrossCompanies()->count());
    }

    public function test_bookkeeper_without_an_active_company_still_cannot_see_other_companies(): void
    {
        $assigned = Company::factory()->create();
        $other = Company::factory()->create();

        BankAccount::factory()->count(2)->create(['company_id' => $assigned->id]);
        BankAccount::factory()->count(4)->create(['company_id' => $other->id]);

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($assigned);

        $this->actingAs($bookkeeper);

        // Resolution falls back to the only company this user may reach.
        $this->assertSame($assigned->id, app(CurrentCompany::class)->id());
        $this->assertSame(2, BankAccount::count());
    }

    public function test_bookkeeper_with_no_assignments_sees_nothing(): void
    {
        $company = Company::factory()->create();
        BankAccount::factory()->count(3)->create(['company_id' => $company->id]);

        $this->actingAs(User::factory()->bookkeeper()->create());

        $this->assertNull(app(CurrentCompany::class)->id());
        $this->assertSame(0, BankAccount::count());
    }

    public function test_company_id_is_filled_from_the_active_company(): void
    {
        $company = Company::factory()->create();

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($company);

        $account = BankAccount::create([
            'bank_name' => 'Banca Intesa',
            'account_number' => '160000000000000018',
        ]);

        $this->assertSame($company->id, $account->company_id);
    }

    public function test_the_active_company_cannot_be_set_to_an_unreachable_one(): void
    {
        $assigned = Company::factory()->create();
        $forbidden = Company::factory()->create();

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($assigned);

        $this->actingAs($bookkeeper);

        $current = app(CurrentCompany::class);

        $this->assertFalse($current->set($forbidden));
        $this->assertSame($assigned->id, $current->id());
    }

    public function test_a_forged_session_value_does_not_widen_access(): void
    {
        $assigned = Company::factory()->create();
        $forbidden = Company::factory()->create();

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($assigned);

        $this->actingAs($bookkeeper);
        session(['current_company_id' => $forbidden->id]);

        $this->assertSame($assigned->id, app(CurrentCompany::class)->id());
    }

    public function test_queries_are_unscoped_without_an_authenticated_user(): void
    {
        $first = Company::factory()->create();
        $second = Company::factory()->create();

        BankAccount::factory()->create(['company_id' => $first->id]);
        BankAccount::factory()->create(['company_id' => $second->id]);

        // Console commands, queued jobs and seeders run without a user.
        $this->assertSame(2, BankAccount::count());
    }
}
