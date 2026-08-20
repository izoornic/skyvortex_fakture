<?php

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Company::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'companies' => Auth::user()
                ->accessibleCompanies()
                ->when($this->search !== '', function ($query) {
                    $term = '%'.$this->search.'%';

                    $query->where(fn ($q) => $q
                        ->where('name', 'like', $term)
                        ->orWhere('short_name', 'like', $term)
                        ->orWhere('pib', 'like', $term));
                })
                ->withCount('bankAccounts')
                ->orderBy('name')
                ->paginate(config('global.paginate')),
            'canCreate' => Auth::user()->can('create', Company::class),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Pravna lica</flux:heading>
            <flux:subheading>Izdavaoci u čije ime se izdaju fakture</flux:subheading>
        </div>

        @if ($canCreate)
            <flux:button variant="primary" icon="plus" :href="route('companies.create')" wire:navigate>
                Novo pravno lice
            </flux:button>
        @endif
    </div>

    <div class="mt-6 max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Naziv ili PIB" />
    </div>

    <div class="mt-4">
        @if ($companies->isEmpty())
            <flux:callout icon="building-office">
                <flux:callout.heading>Nema pravnih lica</flux:callout.heading>
                <flux:callout.text>
                    @if ($canCreate)
                        Dodaj prvo pravno lice da bi moglo da se fakturiše u njegovo ime.
                    @else
                        Administrator ti još nije dodelio nijedno pravno lice.
                    @endif
                </flux:callout.text>
            </flux:callout>
        @else
            <flux:table :paginate="$companies">
                <flux:table.columns>
                    <flux:table.column>Naziv</flux:table.column>
                    <flux:table.column>PIB</flux:table.column>
                    <flux:table.column>Mesto</flux:table.column>
                    <flux:table.column>PDV</flux:table.column>
                    <flux:table.column>Računi</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($companies as $company)
                        <flux:table.row :key="$company->id">
                            <flux:table.cell class="whitespace-normal">
                                <div class="font-medium">{{ $company->name }}</div>
                                {{-- The switcher and every other screen show the short name,
                                     so it has to be visible here too or the same company
                                     looks like two different ones. --}}
                                @if ($company->short_name && $company->short_name !== $company->name)
                                    <div class="text-xs text-zinc-500">{{ $company->short_name }}</div>
                                @endif
                                @unless ($company->is_active)
                                    <flux:badge size="sm" color="zinc">neaktivno</flux:badge>
                                @endunless
                            </flux:table.cell>
                            <flux:table.cell variant="strong">{{ $company->pib }}</flux:table.cell>
                            <flux:table.cell>{{ $company->city }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$company->in_vat_system ? 'lime' : 'zinc'">
                                    {{ $company->in_vat_system ? 'u sistemu' : 'van sistema' }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $company->bank_accounts_count }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $company)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square"
                                        :href="route('companies.edit', $company)" wire:navigate>
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
</section>
