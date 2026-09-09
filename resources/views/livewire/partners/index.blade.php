<?php

use App\Enums\PartnerType;
use App\Models\Partner;
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

    #[Url(as: 'tip', except: '')]
    public string $type = '';

    #[Url(as: 'grupa', except: '')]
    public string $groupId = '';

    #[Url(as: 'neaktivni', except: false)]
    public bool $includeInactive = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Partner::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'groupId', 'includeInactive'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        return [
            'company' => app(CurrentCompany::class)->get(),
            'partners' => Partner::query()
                ->when($this->search !== '', fn ($query) => $query->search($this->search))
                ->when($this->type !== '', fn ($query) => $query->ofType(PartnerType::from($this->type)))
                ->when($this->groupId !== '', fn ($query) => $query->inGroup((int) $this->groupId))
                ->when(! $this->includeInactive, fn ($query) => $query->active())
                ->with('partnerGroup')
                ->orderBy('name')
                ->paginate(config('global.paginate')),
            'types' => PartnerType::options(),
            'groups' => PartnerGroup::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Partneri</flux:heading>
            <flux:subheading>
                Primaoci faktura{{ $company ? ' — '.$company->displayName() : '' }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button icon="arrow-up-tray" :href="route('partners.import')" wire:navigate>Uvoz</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('partners.create')" wire:navigate>Novi partner</flux:button>
        </div>
    </div>

    @if (! $company)
        <flux:callout icon="building-office" class="mt-6">
            <flux:callout.heading>Nije izabrano pravno lice</flux:callout.heading>
            <flux:callout.text>Partneri se vode po pravnom licu. Izaberi jedno u bočnoj traci.</flux:callout.text>
        </flux:callout>
    @else
        <div class="mt-6 flex flex-wrap items-end gap-4">
            <flux:input class="max-w-xs" wire:model.live.debounce.300ms="search"
                icon="magnifying-glass" placeholder="Naziv, PIB, matični broj ili mesto" />

            <flux:select class="max-w-48" wire:model.live="type">
                <flux:select.option value="" :selected="$type === ''">Svi tipovi</flux:select.option>
                @foreach ($types as $value => $label)
                    <flux:select.option value="{{ $value }}" :selected="$value === $type">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($groups->isNotEmpty())
                <flux:select class="max-w-56" wire:model.live="groupId">
                    <flux:select.option value="" :selected="$groupId === ''">Sve grupe</flux:select.option>
                    @foreach ($groups as $groupOption)
                        <flux:select.option value="{{ $groupOption->id }}"
                            :selected="(string) $groupOption->id === $groupId">
                            {{ $groupOption->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:checkbox wire:model.live="includeInactive" label="Prikaži i neaktivne" />
        </div>

        <div class="mt-4">
            @if ($partners->isEmpty())
                <flux:callout icon="users">
                    <flux:callout.heading>Nema partnera</flux:callout.heading>
                    <flux:callout.text>
                        Dodaj partnera ručno ili uvezi listu iz CSV fajla.
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:table :paginate="$partners">
                    <flux:table.columns>
                        <flux:table.column>Naziv</flux:table.column>
                        <flux:table.column>Tip</flux:table.column>
                        <flux:table.column>PIB / VAT</flux:table.column>
                        <flux:table.column>Grupa</flux:table.column>
                        <flux:table.column>Mesto</flux:table.column>
                        <flux:table.column>Rok</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($partners as $partner)
                            <flux:table.row :key="$partner->id">
                                <flux:table.cell class="whitespace-normal">
                                    <div class="font-medium">{{ $partner->name }}</div>
                                    @unless ($partner->is_active)
                                        <flux:badge size="sm" color="zinc">neaktivan</flux:badge>
                                    @endunless
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" color="zinc">{{ $partner->type->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell variant="strong">{{ $partner->identifier() ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="whitespace-normal">
                                    @if ($partner->partnerGroup)
                                        <flux:badge size="sm" color="sky">{{ $partner->partnerGroup->name }}</flux:badge>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $partner->city ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $partner->payment_days }} d</flux:table.cell>
                                <flux:table.cell align="end">
                                    @can('update', $partner)
                                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                                            :href="route('partners.edit', $partner)" wire:navigate>
                                            Izmeni
                                        </flux:button>
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif
</section>
