<?php

use App\Models\PartnerGroup;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'neaktivne', except: false)]
    public bool $includeInactive = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', PartnerGroup::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'includeInactive'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Switching a group off hides it from the default list, so the filter is
     * turned on to keep the row where the user just clicked.
     */
    public function toggleActive(int $id): void
    {
        $group = PartnerGroup::findOrFail($id);

        Gate::authorize('update', $group);

        $group->update(['is_active' => ! $group->is_active]);

        if ($group->is_active) {
            session()->now('status', 'Grupa „'.$group->name.'” je ponovo aktivna.');

            return;
        }

        $message = 'Grupa „'.$group->name.'” je isključena — više se ne nudi za objedinjenu pošiljku.';

        if (! $this->includeInactive) {
            $this->includeInactive = true;
            $message .= ' Uključen je prikaz neaktivnih da bi ostala na spisku.';
        }

        session()->now('status', $message);
    }

    /**
     * A group that still has members is not deleted — its partners would lose
     * where their invoices are delivered.
     */
    public function delete(int $id): void
    {
        $group = PartnerGroup::withCount('partners')->findOrFail($id);

        if ($group->partners_count > 0) {
            $this->addError('group', 'Grupa „'.$group->name.'” ima članove. Prvo ih prebaci iz grupe.');

            return;
        }

        Gate::authorize('delete', $group);

        $group->delete();

        session()->now('status', 'Grupa je obrisana.');
    }

    public function with(): array
    {
        return [
            'company' => app(CurrentCompany::class)->get(),
            'groups' => PartnerGroup::query()
                ->when($this->search !== '', fn ($query) => $query->search($this->search))
                ->when(! $this->includeInactive, fn ($query) => $query->active())
                ->withCount('partners')
                ->orderBy('name')
                ->paginate(config('global.paginate')),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Grupe partnera</flux:heading>
            <flux:subheading>
                Jedna adresa, jedan PDF sa svim fakturama grupe{{ $company ? ' — '.$company->displayName() : '' }}
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button icon="envelope" :href="route('partner-groups.bundle')" wire:navigate>
                Objedinjena pošiljka
            </flux:button>
            <flux:button variant="primary" icon="plus" :href="route('partner-groups.create')" wire:navigate>
                Nova grupa
            </flux:button>
        </div>
    </div>

    @if (! $company)
        <flux:callout icon="building-office" class="mt-6">
            <flux:callout.heading>Nije izabrano pravno lice</flux:callout.heading>
            <flux:callout.text>Grupe se vode po pravnom licu. Izaberi jedno u bočnoj traci.</flux:callout.text>
        </flux:callout>
    @else
        @error('group')
            <flux:callout icon="exclamation-triangle" color="red" class="mt-6">
                <flux:callout.text>{{ $message }}</flux:callout.text>
            </flux:callout>
        @enderror

        @if (session('status'))
            <flux:callout icon="check-circle" color="lime" class="mt-6">
                <flux:callout.text>{{ session('status') }}</flux:callout.text>
            </flux:callout>
        @endif

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <flux:input class="max-w-xs" wire:model.live.debounce.300ms="search"
                icon="magnifying-glass" placeholder="Naziv, e-mail ili kontakt" />

            <flux:checkbox wire:model.live="includeInactive" label="Prikaži i neaktivne" />
        </div>

        <div class="mt-4">
            @if ($groups->isEmpty())
                <flux:callout icon="user-group">
                    <flux:callout.heading>Nema grupa</flux:callout.heading>
                    <flux:callout.text>
                        Grupa okuplja partnere koji se plaćaju sa jednog mesta — više stambenih
                        zajednica pod istim upravnikom, na primer — i prima jedan PDF sa svim
                        njihovim fakturama za mesec.
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:table :paginate="$groups">
                    <flux:table.columns>
                        <flux:table.column>Grupa</flux:table.column>
                        <flux:table.column>Adresa za slanje</flux:table.column>
                        <flux:table.column align="end">Članova</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($groups as $group)
                            <flux:table.row :key="$group->id">
                                <flux:table.cell class="whitespace-normal">
                                    <div class="font-medium">{{ $group->name }}</div>
                                    @if ($group->contact_person)
                                        <div class="text-xs text-zinc-500">{{ $group->contact_person }}</div>
                                    @endif
                                    @unless ($group->is_active)
                                        <flux:badge size="sm" color="zinc">neaktivna</flux:badge>
                                    @endunless
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($group->canReceiveMail())
                                        {{ $group->email }}
                                    @else
                                        <flux:badge size="sm" color="amber">bez adrese</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" variant="strong">{{ $group->partners_count }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" icon="document-text"
                                            :href="route('partner-groups.bundle', ['grupa' => $group->id])" wire:navigate>
                                            Pošiljka
                                        </flux:button>

                                        @can('update', $group)
                                            <flux:button size="sm" variant="ghost"
                                                :icon="$group->is_active ? 'pause' : 'play'"
                                                wire:click="toggleActive({{ $group->id }})" />

                                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                                :href="route('partner-groups.edit', $group)" wire:navigate />
                                        @endcan

                                        @if ($group->partners_count === 0)
                                            @can('delete', $group)
                                                <flux:button size="sm" variant="ghost" icon="trash"
                                                    wire:click="delete({{ $group->id }})"
                                                    wire:confirm="Obrisati grupu „{{ $group->name }}”?" />
                                            @endcan
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif
</section>
