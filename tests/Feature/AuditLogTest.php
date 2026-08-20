<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_company_is_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $company = Company::factory()->create(['name' => 'Prva firma']);

        $audit = AuditLog::where('auditable_type', Company::class)
            ->where('auditable_id', $company->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($company->id, $audit->company_id);
        $this->assertSame('Prva firma', $audit->new_values['name']);
    }

    public function test_updating_records_only_what_changed(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $company = Company::factory()->create(['city' => 'Beograd']);

        $company->update(['city' => 'Niš']);

        $audit = AuditLog::where('auditable_id', $company->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(['city' => 'Niš'], $audit->new_values);
        $this->assertSame(['city' => 'Beograd'], $audit->old_values);
    }

    public function test_an_update_that_changes_nothing_writes_no_row(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $company = Company::factory()->create(['city' => 'Beograd']);

        $company->update(['city' => 'Beograd']);

        $this->assertSame(0, AuditLog::where('event', 'updated')->count());
    }

    public function test_password_never_reaches_the_audit_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $user = User::factory()->bookkeeper()->create();

        $audit = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('password', $audit->new_values);
        $this->assertArrayNotHasKey('remember_token', $audit->new_values);
    }
}
