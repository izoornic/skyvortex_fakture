<?php

namespace Tests\Feature\PartnerGroups;

use App\Models\Company;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PartnerGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create();

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_a_group_is_created_for_the_active_company(): void
    {
        Volt::test('partner-groups.form')
            ->set('name', 'Uprava Petrović')
            ->set('email', 'racunovodstvo@uprava.rs')
            ->set('contact_person', 'Milica Petrović')
            ->call('save')
            ->assertHasNoErrors();

        $group = PartnerGroup::acrossCompanies()->sole();

        $this->assertSame('Uprava Petrović', $group->name);
        $this->assertSame('racunovodstvo@uprava.rs', $group->email);
        $this->assertSame($this->company->id, $group->company_id);
        $this->assertTrue($group->is_active);
    }

    public function test_two_groups_of_the_same_company_cannot_share_a_name(): void
    {
        PartnerGroup::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Uprava Petrović',
        ]);

        Volt::test('partner-groups.form')
            ->set('name', 'Uprava Petrović')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(1, PartnerGroup::acrossCompanies()->count());
    }

    public function test_the_same_name_is_free_at_another_company(): void
    {
        $other = Company::factory()->create();
        PartnerGroup::factory()->create(['company_id' => $other->id, 'name' => 'Uprava Petrović']);

        Volt::test('partner-groups.form')
            ->set('name', 'Uprava Petrović')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, PartnerGroup::acrossCompanies()->count());
    }

    public function test_a_group_of_another_company_is_out_of_reach(): void
    {
        $other = Company::factory()->create();
        $foreign = PartnerGroup::factory()->create(['company_id' => $other->id]);

        $this->assertNull(PartnerGroup::query()->find($foreign->id));
        $this->assertSame(0, PartnerGroup::query()->count());
    }

    public function test_a_group_with_members_is_not_deleted(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);
        Partner::factory()->create([
            'company_id' => $this->company->id,
            'partner_group_id' => $group->id,
        ]);

        Volt::test('partner-groups.index')
            ->call('delete', $group->id)
            ->assertHasErrors('group');

        $this->assertNotNull($group->fresh());
    }

    public function test_an_empty_group_is_deleted(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);

        Volt::test('partner-groups.index')
            ->call('delete', $group->id)
            ->assertHasNoErrors();

        $this->assertNull($group->fresh());
    }

    /**
     * Switching a group off hides it from the default list, so the row has to
     * be kept visible or the click looks like a deletion.
     */
    public function test_switching_a_group_off_turns_on_the_inactive_filter(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);

        Volt::test('partner-groups.index')
            ->assertSet('includeInactive', false)
            ->call('toggleActive', $group->id)
            ->assertSet('includeInactive', true)
            ->assertSee($group->name);

        $this->assertFalse($group->fresh()->is_active);
    }

    public function test_a_partner_joins_a_group_from_its_own_form(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);

        Volt::test('partners.form')
            ->set('name', 'SZ Nemanjina 12')
            ->set('pib', '123456789')
            ->set('registration_number', '12345678')
            ->set('partner_group_id', (string) $group->id)
            ->call('save')
            ->assertHasNoErrors();

        $partner = Partner::acrossCompanies()->sole();

        $this->assertSame($group->id, $partner->partner_group_id);
        $this->assertSame($group->id, $partner->partnerGroup->id);
    }

    public function test_a_partner_cannot_join_a_group_of_another_company(): void
    {
        $other = Company::factory()->create();
        $foreign = PartnerGroup::factory()->create(['company_id' => $other->id]);

        Volt::test('partners.form')
            ->set('name', 'SZ Nemanjina 12')
            ->set('pib', '123456789')
            ->set('registration_number', '12345678')
            ->set('partner_group_id', (string) $foreign->id)
            ->call('save')
            ->assertHasErrors('partner_group_id');

        $this->assertSame(0, Partner::acrossCompanies()->count());
    }

    public function test_a_partner_leaves_a_group_by_choosing_none(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);
        $partner = Partner::factory()->create([
            'company_id' => $this->company->id,
            'partner_group_id' => $group->id,
        ]);

        Volt::test('partners.form', ['partner' => $partner])
            ->assertSet('partner_group_id', (string) $group->id)
            ->set('partner_group_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($partner->fresh()->partner_group_id);
    }

    public function test_the_partner_list_filters_by_group_and_shows_it(): void
    {
        $group = PartnerGroup::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Uprava Petrović',
        ]);

        Partner::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'SZ Nemanjina 12',
            'partner_group_id' => $group->id,
        ]);
        Partner::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Samostalni klijent d.o.o.',
        ]);

        Volt::test('partners.index')
            ->assertSee('SZ Nemanjina 12')
            ->assertSee('Samostalni klijent d.o.o.')
            ->assertSee('Uprava Petrović')
            ->set('groupId', (string) $group->id)
            ->assertSee('SZ Nemanjina 12')
            ->assertDontSee('Samostalni klijent d.o.o.');
    }

    /**
     * Deleting a group must not take its partners with it — the members stay,
     * only the delivery channel is gone.
     */
    public function test_deleting_a_group_leaves_its_partners_behind(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);
        $partner = Partner::factory()->create([
            'company_id' => $this->company->id,
            'partner_group_id' => $group->id,
        ]);

        $group->delete();

        $this->assertNotNull($partner->fresh());
        $this->assertNull($partner->fresh()->partner_group_id);
    }

    public function test_a_bookkeeper_without_the_company_cannot_reach_its_groups(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);

        $bookkeeper = User::factory()->bookkeeper()->create();

        $this->assertFalse($bookkeeper->can('view', $group));
        $this->assertFalse($bookkeeper->can('update', $group));
    }

    public function test_a_bookkeeper_assigned_to_the_company_keeps_its_groups(): void
    {
        $group = PartnerGroup::factory()->create(['company_id' => $this->company->id]);

        $bookkeeper = User::factory()->bookkeeper()->create();
        $bookkeeper->companies()->attach($this->company);

        $this->assertTrue($bookkeeper->fresh()->can('update', $group));
    }
}
