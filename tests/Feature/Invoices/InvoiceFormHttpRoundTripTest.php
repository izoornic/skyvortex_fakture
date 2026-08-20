<?php

namespace Tests\Feature\Invoices;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Drives the form the way the browser does: fetch the page, read the snapshot
 * Livewire embedded in it, then post to /livewire/update with the values a
 * <select> actually sends — strings.
 *
 * Volt::test() calls the component directly and skips hydration, so it cannot
 * catch a mistake that only shows up on the wire.
 */
class InvoiceFormHttpRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create();
        BankAccount::factory()->primary()->create(['company_id' => $this->company->id]);
        $this->partner = Partner::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_a_partner_chosen_in_the_browser_survives_the_round_trip(): void
    {
        $snapshot = $this->formSnapshot();

        // Exactly what the select sends: the id as a string.
        $response = $this->livewireUpdate($snapshot, ['partner_id' => (string) $this->partner->id]);

        $response->assertOk();

        $state = $this->stateFrom($response);

        $this->assertSame(
            $this->partner->id,
            $state['partner_id'],
            'Partner izabran u pregledaču nije stigao do servera.',
        );
    }

    public function test_saving_after_choosing_a_partner_does_not_complain_that_it_is_missing(): void
    {
        $snapshot = $this->formSnapshot();

        $afterPartner = $this->livewireUpdate($snapshot, ['partner_id' => (string) $this->partner->id]);

        $response = $this->livewireUpdate(
            $this->snapshotFrom($afterPartner),
            [
                'items.0.name' => 'Održavanje',
                'items.0.quantity' => '1',
                'items.0.unit_price' => '1000',
            ],
            [['path' => '', 'method' => 'save', 'params' => []]],
        );

        $response->assertOk();

        $body = $response->json();
        $errors = data_get($body, 'components.0.effects.errors', []);

        $this->assertSame([], $errors, 'Validacija je prijavila grešku: '.json_encode($errors));
        $this->assertDatabaseHas('invoices', ['partner_id' => $this->partner->id]);
    }

    /**
     * Snapshot of the invoice form component as embedded in the page.
     */
    private function formSnapshot(): array
    {
        $html = $this->get(route('invoices.create'))->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $raw) {
            $snapshot = json_decode(html_entity_decode($raw, ENT_QUOTES), true);

            if (isset($snapshot['data']['partner_id']) || array_key_exists('partner_id', $snapshot['data'] ?? [])) {
                return $snapshot;
            }
        }

        $this->fail('Snapshot forme fakture nije pronađen na stranici.');
    }

    private function livewireUpdate(array $snapshot, array $updates, array $calls = [])
    {
        // Livewire 4 hashes the endpoint path, and the request is refused
        // without the header its JavaScript sends.
        return $this->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => $updates,
                'calls' => $calls,
            ]],
        ]);
    }

    private function snapshotFrom($response): array
    {
        return json_decode($response->json('components.0.snapshot'), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function stateFrom($response): array
    {
        return $this->snapshotFrom($response)['data'];
    }
}
