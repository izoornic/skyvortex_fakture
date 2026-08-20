<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookkeeper_cannot_reach_user_administration(): void
    {
        $this->actingAs(User::factory()->bookkeeper()->create())
            ->get('/users')
            ->assertForbidden();
    }

    public function test_admin_can_list_users(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        User::factory()->bookkeeper()->create(['name' => 'Petar Petrović']);

        $this->get('/users')
            ->assertOk()
            ->assertSee('Petar Petrović');
    }

    public function test_admin_assigns_companies_to_a_bookkeeper(): void
    {
        $first = Company::factory()->create();
        $second = Company::factory()->create();
        Company::factory()->create();

        $bookkeeper = User::factory()->bookkeeper()->create();

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('users.form', ['user' => $bookkeeper])
            ->set('companyIds', [$first->id, $second->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $bookkeeper->fresh()->companies()->pluck('companies.id')->all()
        );
    }

    public function test_promoting_to_admin_clears_stale_assignments(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->bookkeeper()->create();
        $user->companies()->attach($company);

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('users.form', ['user' => $user])
            ->set('role', UserRole::Admin->value)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame(0, $user->companies()->count());
        $this->assertTrue($user->canAccessCompany($company));
    }

    public function test_editing_a_user_without_a_password_keeps_the_old_one(): void
    {
        $user = User::factory()->bookkeeper()->create();
        $original = $user->password;

        $this->actingAs(User::factory()->admin()->create());

        Volt::test('users.form', ['user' => $user])
            ->set('name', 'Novo ime')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Novo ime', $user->name);
        $this->assertSame($original, $user->password);
    }

    public function test_an_admin_may_not_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->bookkeeper()->create();

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertTrue($admin->can('delete', $other));
    }
}
