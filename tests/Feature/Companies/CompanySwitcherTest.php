<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanySwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookkeeper_only_sees_assigned_companies_in_the_switcher(): void
    {
        $assigned = Company::factory()->create(['name' => 'Alfa', 'short_name' => 'Alfa']);
        Company::factory()->create(['name' => 'Beta', 'short_name' => 'Beta']);

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($assigned);

        $this->actingAs($bookkeeper);

        Volt::test('company-switcher')
            ->assertSee('Alfa')
            ->assertDontSee('Beta');
    }

    public function test_switching_changes_the_active_company(): void
    {
        $first = Company::factory()->create(['name' => 'Alfa']);
        $second = Company::factory()->create(['name' => 'Beta']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Volt::test('company-switcher')->call('select', $second->id);

        app(CurrentCompany::class)->refresh();

        $this->assertSame($second->id, app(CurrentCompany::class)->id());
        $this->assertNotSame($first->id, app(CurrentCompany::class)->id());
    }

    public function test_switching_to_an_unassigned_company_is_ignored(): void
    {
        $assigned = Company::factory()->create(['name' => 'Alfa']);
        $forbidden = Company::factory()->create(['name' => 'Beta']);

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($assigned);

        $this->actingAs($bookkeeper);

        Volt::test('company-switcher')->call('select', $forbidden->id);

        app(CurrentCompany::class)->refresh();

        $this->assertSame($assigned->id, app(CurrentCompany::class)->id());
    }

    public function test_inactive_companies_are_not_offered(): void
    {
        Company::factory()->create(['name' => 'Aktivna', 'short_name' => 'Aktivna']);
        Company::factory()->inactive()->create(['name' => 'Zatvorena', 'short_name' => 'Zatvorena']);

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('company-switcher')
            ->assertSee('Aktivna')
            ->assertDontSee('Zatvorena');
    }
}
