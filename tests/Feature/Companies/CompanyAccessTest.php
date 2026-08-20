<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/companies')->assertRedirect('/login');
    }

    public function test_bookkeeper_sees_only_assigned_companies(): void
    {
        $assigned = Company::factory()->create(['name' => 'Dodeljena firma']);
        $other = Company::factory()->create(['name' => 'Tuđa firma']);

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($assigned);

        $this->actingAs($bookkeeper)
            ->get('/companies')
            ->assertOk()
            ->assertSee('Dodeljena firma')
            ->assertDontSee('Tuđa firma');
    }

    public function test_admin_sees_every_company(): void
    {
        Company::factory()->create(['name' => 'Prva firma']);
        Company::factory()->create(['name' => 'Druga firma']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/companies')
            ->assertOk()
            ->assertSee('Prva firma')
            ->assertSee('Druga firma');
    }

    public function test_bookkeeper_cannot_open_the_create_form(): void
    {
        $this->actingAs(User::factory()->bookkeeper()->create())
            ->get('/companies/create')
            ->assertForbidden();
    }

    public function test_bookkeeper_cannot_edit_a_company(): void
    {
        $company = Company::factory()->create();

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($company);

        $this->actingAs($bookkeeper)
            ->get("/companies/{$company->id}/edit")
            ->assertForbidden();
    }

    public function test_admin_can_open_the_create_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/companies/create')
            ->assertOk();
    }

    public function test_accessible_company_ids_follow_the_role(): void
    {
        $first = Company::factory()->create();
        $second = Company::factory()->create();

        $admin = User::factory()->admin()->create();
        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($first);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $admin->accessibleCompanyIds()
        );

        $this->assertSame([$first->id], $bookkeeper->accessibleCompanyIds());
        $this->assertTrue($bookkeeper->canAccessCompany($first));
        $this->assertFalse($bookkeeper->canAccessCompany($second));
    }
}
