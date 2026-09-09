<?php

use App\Actions\Contracts\GenerateContractDrafts;
use App\Models\Contract;
use App\Support\CurrentCompany;
use App\Support\InvoiceTotals;
use App\Support\PeriodLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'neaktivni', except: false)]
    public bool $includeInactive = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Contract::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'includeInactive'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Pausing hides the contract from the default list, so the filter is
     * switched on to keep the row where the user just clicked.
     */
    public function toggleActive(int $id): void
    {
        $contract = Contract::findOrFail($id);

        Gate::authorize('update', $contract);

        $contract->update(['is_active' => ! $contract->is_active]);

        if ($contract->is_active) {
            session()->now('status', 'Ugovor „'.$contract->name.'" je ponovo aktivan.');

            return;
        }

        $message = 'Ugovor „'.$contract->name.'" je pauziran — neće više praviti nacrte.';

        if (! $this->includeInactive) {
            $this->includeInactive = true;
            $message .= ' Uključen je prikaz neaktivnih da bi ostao na spisku.';
        }

        session()->now('status', $message);
    }

    /**
     * Makes the draft this contract owes right now, without waiting for the
     * scheduled run.
     */
    public function generateNow(int $id, GenerateContractDrafts $drafts): void
    {
        $contract = Contract::with('items')->findOrFail($id);

        Gate::authorize('generate', $contract);

        if ($contract->items->isEmpty()) {
            $this->addError('contract', 'Ugovor „'.$contract->name.'" nema stavki.');

            return;
        }

        $invoice = $drafts->draftFor($contract, now());

        $this->redirect(route('invoices.edit', $invoice), navigate: true);
    }

    public function delete(int $id): void
    {
        $contract = Contract::findOrFail($id);

        Gate::authorize('delete', $contract);

        $contract->delete();
    }

    /**
     * The search and the inactive switch live here so the listing and both
     * totals below it always describe the same set of contracts.
     */
    protected function filteredContracts(): Builder
    {
        return Contract::query()
            ->when(! $this->includeInactive, fn ($query) => $query->active())
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';

                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('partner', fn ($p) => $p->where('name', 'like', $term)));
            });
    }

    /**
     * @param  iterable<int, Contract>  $contracts
     */
    protected function monthlyTotalFor(iterable $contracts): float
    {
        $total = 0.0;

        foreach ($contracts as $contract) {
            $total += InvoiceTotals::forDocument(
                $contract->items->map(fn ($item) => $item->toInvoiceItem())->all()
            )['total'];
        }

        return round($total, 2);
    }

    public function with(): array
    {
        $contracts = $this->filteredContracts()
            ->with(['partner', 'items'])
            ->withCount('invoices')
            ->orderBy('name')
            ->paginate(config('global.paginate'));

        return [
            'company' => app(CurrentCompany::class)->get(),
            'contracts' => $contracts,
            'monthlyTotal' => $this->monthlyTotalFor($contracts->getCollection()),
            'monthlyTotalAll' => $this->monthlyTotalFor(
                $this->filteredContracts()->with('items')->get()
            ),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Ugovori</flux:heading>
            <flux:subheading>{{ $company?->displayName() }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button icon="calendar-days" :href="route('invoices.monthly')" wire:navigate>
                Mesečno fakturisanje
            </flux:button>
            <flux:button variant="primary" icon="plus" :href="route('contracts.create')" wire:navigate>
                Novi ugovor
            </flux:button>
        </div>
    </div>

    @if (! $company)
        <flux:callout icon="building-office" class="mt-6">
            <flux:callout.heading>Nije izabrano pravno lice</flux:callout.heading>
            <flux:callout.text>Izaberi pravno lice u bočnoj traci.</flux:callout.text>
        </flux:callout>
    @else
        @error('contract')
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
                icon="magnifying-glass" placeholder="Naziv, broj ili partner" />

            <flux:checkbox wire:model.live="includeInactive" label="Prikaži i neaktivne" />

            <div class="ms-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500">
                <span>
                    Mesečno na ovoj strani:
                    <strong class="text-zinc-900 dark:text-white">{{ number_format($monthlyTotal, 2, ',', '.') }}</strong>
                </span>
                <span>
                    Ukupno svi ugovori:
                    <strong class="text-zinc-900 dark:text-white">{{ number_format($monthlyTotalAll, 2, ',', '.') }}</strong>
                </span>
            </div>
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Ugovor</flux:table.column>
                <flux:table.column>Partner</flux:table.column>
                <flux:table.column>Obračun</flux:table.column>
                <flux:table.column>Sledeći nacrt</flux:table.column>
                <flux:table.column align="end">Mesečno</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($contracts as $contract)
                    @php
                        $next = $contract->nextRun();
                        $value = \App\Support\InvoiceTotals::forDocument(
                            $contract->items->map(fn ($item) => $item->toInvoiceItem())->all()
                        );
                    @endphp
                    <flux:table.row :key="$contract->id">
                        <flux:table.cell>
                            <a href="{{ route('contracts.edit', $contract) }}" wire:navigate class="font-medium hover:underline">
                                {{ $contract->name }}
                            </a>
                            @unless ($contract->is_active)
                                <flux:badge size="sm" color="zinc" class="ms-2">neaktivan</flux:badge>
                            @endunless
                            @if ($contract->reference)
                                <div class="text-xs text-zinc-500">{{ $contract->reference }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $contract->partner->name }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $contract->billing_mode->shortLabel() }}
                            <div class="text-xs text-zinc-500">{{ $contract->generation_day }}. u mesecu</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($next)
                                {{ $next['on']->format('d.m.Y.') }}
                                <div class="text-xs text-zinc-500">za {{ \App\Support\PeriodLabel::forDate($next['period']) }}</div>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            {{ number_format($value['total'], 2, ',', '.') }} {{ $contract->currency }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @if ($contract->is_active)
                                    <flux:button size="sm" variant="ghost" icon="document-plus"
                                        wire:click="generateNow({{ $contract->id }})"
                                        wire:confirm="Napraviti nacrt za ovaj ugovor?" />
                                @endif

                                <flux:button size="sm" variant="ghost"
                                    :icon="$contract->is_active ? 'pause' : 'play'"
                                    wire:click="toggleActive({{ $contract->id }})" />

                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    :href="route('contracts.edit', $contract)" wire:navigate />

                                @if ($contract->invoices_count === 0)
                                    <flux:button size="sm" variant="ghost" icon="trash"
                                        wire:click="delete({{ $contract->id }})"
                                        wire:confirm="Obrisati ugovor?" />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            Nema ugovora. Ugovor pamti šta se svakog meseca fakturiše, pa se mesec svede na pregled.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">{{ $contracts->links() }}</div>
    @endif
</section>
