<?php

use App\Actions\PartnerGroups\CollectGroupInvoices;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PartnerGroup;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'god')]
    public int $year = 0;

    #[Url(as: 'mes')]
    public ?int $month = null;

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'grupa', except: '')]
    public string $groupId = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Invoice::class);

        $this->year = $this->year ?: now()->year;
        $this->month ??= now()->month;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['year', 'month', 'status', 'search', 'groupId'], true)) {
            $this->resetPage();
        }
    }

    public function previousMonth(): void
    {
        $date = now()->setDate($this->year, $this->month ?: 1, 1)->subMonth();

        $this->year = $date->year;
        $this->month = $date->month;
        $this->resetPage();
    }

    public function nextMonth(): void
    {
        $date = now()->setDate($this->year, $this->month ?: 1, 1)->addMonth();

        $this->year = $date->year;
        $this->month = $date->month;
        $this->resetPage();
    }

    public function showWholeYear(): void
    {
        $this->month = null;
        $this->resetPage();
    }

    private function baseQuery()
    {
        return Invoice::query()
            ->inPeriod($this->year, $this->month)
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->groupId !== '', fn ($q) => $q->whereHas(
                'partner',
                fn ($p) => $p->where('partner_group_id', (int) $this->groupId)
            ))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';

                $query->where(fn ($q) => $q
                    ->where('number', 'like', $term)
                    ->orWhereHas('partner', fn ($p) => $p->where('name', 'like', $term)));
            });
    }

    public function with(): array
    {
        $countable = (clone $this->baseQuery())->countable();

        $selectedGroup = $this->groupId === ''
            ? null
            : PartnerGroup::query()->find((int) $this->groupId);

        return [
            'company' => app(CurrentCompany::class)->get(),
            'invoices' => $this->baseQuery()
                ->with('partner')
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->paginate(config('global.paginate')),
            'statuses' => InvoiceStatus::options(),
            'groups' => PartnerGroup::query()->orderBy('name')->get(),
            'selectedGroup' => $selectedGroup,
            // What the bundle would carry: the period only, never the status or
            // the search term on the screen.
            'bundleCount' => $selectedGroup
                ? app(CollectGroupInvoices::class)->handle($selectedGroup, $this->year, $this->month)->count()
                : 0,
            'summary' => [
                'count' => (clone $countable)->count(),
                'total_rsd' => (float) (clone $countable)->sum('total_rsd'),
                'drafts' => (clone $this->baseQuery())->drafts()->count(),
            ],
            'periodLabel' => $this->month
                ? now()->setDate($this->year, $this->month, 1)->translatedFormat('F Y').'.'
                : $this->year.'.',
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Fakture</flux:heading>
            <flux:subheading>{{ $company?->displayName() }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('invoices.create')" wire:navigate>
            Novi dokument
        </flux:button>
    </div>

    @if (! $company)
        <flux:callout icon="building-office" class="mt-6">
            <flux:callout.heading>Nije izabrano pravno lice</flux:callout.heading>
            <flux:callout.text>Izaberi pravno lice u bočnoj traci.</flux:callout.text>
        </flux:callout>
    @else
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1">
                <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previousMonth" />
                <span class="min-w-40 text-center font-medium">{{ $periodLabel }}</span>
                <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="nextMonth" />
            </div>

            @if ($month)
                <flux:button size="sm" variant="ghost" wire:click="showWholeYear">Cela godina</flux:button>
            @endif

            <flux:input class="max-w-xs" wire:model.live.debounce.300ms="search"
                icon="magnifying-glass" placeholder="Broj ili partner" />

            <flux:select class="max-w-48" wire:model.live="status">
                <flux:select.option value="" :selected="$status === ''">Svi statusi</flux:select.option>
                @foreach ($statuses as $value => $label)
                    <flux:select.option value="{{ $value }}" :selected="$value === $status">{{ $label }}</flux:select.option>
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

            @if ($selectedGroup && $bundleCount > 0)
                <flux:button size="sm" icon="document-text"
                    :href="route('partner-groups.pdf', ['partnerGroup' => $selectedGroup, 'god' => $year, 'mes' => $month])"
                    target="_blank">
                    Objedinjeni PDF ({{ $bundleCount }})
                </flux:button>
            @endif
        </div>

        @if ($selectedGroup && $bundleCount > 0)
            <div class="mt-2 text-xs text-zinc-500">
                Objedinjeni PDF nosi izdate fakture grupe za {{ $periodLabel }} — status i pretraga
                sa ovog ekrana ne ulaze u dokument.
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            <flux:badge color="zinc">izdato: {{ $summary['count'] }}</flux:badge>
            <flux:badge color="lime">promet: {{ number_format($summary['total_rsd'], 2, ',', '.') }} RSD</flux:badge>
            @if ($summary['drafts'] > 0)
                <flux:badge color="amber">nacrta: {{ $summary['drafts'] }}</flux:badge>
            @endif
        </div>

        <div class="mt-4">
            @if ($invoices->isEmpty())
                <flux:callout icon="document-text">
                    <flux:callout.heading>Nema dokumenata u ovom periodu</flux:callout.heading>
                    <flux:callout.text>Napravi novi dokument ili promeni period.</flux:callout.text>
                </flux:callout>
            @else
                <flux:table :paginate="$invoices">
                    <flux:table.columns>
                        <flux:table.column>Broj</flux:table.column>
                        <flux:table.column>Partner</flux:table.column>
                        <flux:table.column>Izdato</flux:table.column>
                        <flux:table.column>Dospeva</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column align="end">Iznos</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($invoices as $invoice)
                            <flux:table.row :key="$invoice->id">
                                <flux:table.cell variant="strong">
                                    {{ $invoice->displayNumber() }}
                                    @if ($invoice->type->value !== 'faktura')
                                        <div class="text-xs text-zinc-500">{{ $invoice->type->label() }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-normal">{{ $invoice->partner->name }}</flux:table.cell>
                                <flux:table.cell>{{ $invoice->issue_date->format('d.m.Y.') }}</flux:table.cell>
                                <flux:table.cell>{{ $invoice->due_date->format('d.m.Y.') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$invoice->status->color()">
                                        {{ $invoice->status->label() }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end" variant="strong">
                                    {{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        :href="route('invoices.show', $invoice)" wire:navigate />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif
</section>
