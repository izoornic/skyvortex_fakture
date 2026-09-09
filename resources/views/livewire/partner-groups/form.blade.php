<?php

use App\Models\PartnerGroup;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?PartnerGroup $partnerGroup = null;

    public string $name = '';
    public string $email = '';
    public string $contact_person = '';
    public string $phone = '';
    public string $notes = '';
    public bool $is_active = true;

    public function mount(?PartnerGroup $partnerGroup = null): void
    {
        $this->partnerGroup = $partnerGroup?->exists ? $partnerGroup : null;

        if ($this->partnerGroup) {
            Gate::authorize('update', $this->partnerGroup);

            foreach (['name', 'email', 'contact_person', 'phone', 'notes'] as $field) {
                $this->{$field} = (string) $this->partnerGroup->{$field};
            }

            $this->is_active = $this->partnerGroup->is_active;

            return;
        }

        Gate::authorize('create', PartnerGroup::class);
    }

    public function save(): void
    {
        $company = app(CurrentCompany::class)->get();

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('partner_groups', 'name')
                    ->where('company_id', $company->id)
                    ->ignore($this->partnerGroup),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], [
            'name.unique' => 'Grupa sa ovim nazivom već postoji kod ovog pravnog lica.',
        ]);

        foreach (['email', 'contact_person', 'phone', 'notes'] as $optional) {
            $data[$optional] = $data[$optional] ?: null;
        }

        if ($this->partnerGroup) {
            $this->partnerGroup->update($data);
        } else {
            $this->partnerGroup = $company->partnerGroups()->create($data);
        }

        $this->redirect(route('partner-groups.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'members' => $this->partnerGroup
                ? $this->partnerGroup->partners()->orderBy('name')->get()
                : collect(),
        ];
    }
}; ?>

<section class="w-full max-w-3xl">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $partnerGroup ? 'Izmena grupe' : 'Nova grupa partnera' }}</flux:heading>
            <flux:subheading>
                Grupa prima jedan PDF sa svim fakturama svojih partnera za period,
                na jednu adresu. Fakture i dalje glase na pojedinačnog partnera.
            </flux:subheading>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('partner-groups.index')" wire:navigate>
            Nazad
        </flux:button>
    </div>

    <form wire:submit="save" class="mt-6 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:input wire:model="name" label="Naziv grupe" required
                    placeholder="npr. Uprava Petrović — stambene zajednice" />
            </div>

            <flux:input wire:model="email" type="email" label="E-mail za objedinjenu pošiljku"
                placeholder="racunovodstvo@primer.rs" />

            <flux:input wire:model="contact_person" label="Kontakt osoba" />

            <flux:input wire:model="phone" label="Telefon" />

            <div class="flex items-end">
                <flux:checkbox wire:model="is_active" label="Aktivna" />
            </div>

            <div class="sm:col-span-2">
                <flux:textarea wire:model="notes" label="Napomene" rows="3" />
            </div>
        </div>

        @unless ($email)
            <flux:callout icon="exclamation-triangle" color="amber">
                <flux:callout.text>
                    Bez adrese se objedinjeni PDF može preuzeti, ali ne i poslati.
                </flux:callout.text>
            </flux:callout>
        @endunless

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Sačuvaj</flux:button>
            <flux:button variant="ghost" :href="route('partner-groups.index')" wire:navigate>Odustani</flux:button>
        </div>
    </form>

    @if ($partnerGroup)
        <div class="mt-10">
            <flux:heading size="lg">Članovi grupe</flux:heading>
            <flux:subheading>Partner se u grupu dodaje na svom formularu.</flux:subheading>

            @if ($members->isEmpty())
                <flux:callout icon="users" class="mt-4">
                    <flux:callout.text>
                        Grupa još nema članova. Otvori partnera i izaberi ovu grupu u polju „Grupa”.
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>Partner</flux:table.column>
                        <flux:table.column>Tip</flux:table.column>
                        <flux:table.column>PIB / VAT</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($members as $member)
                            <flux:table.row :key="$member->id">
                                <flux:table.cell class="whitespace-normal">{{ $member->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" color="zinc">{{ $member->type->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell variant="strong">{{ $member->identifier() ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square"
                                        :href="route('partners.edit', $member)" wire:navigate />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif
</section>
