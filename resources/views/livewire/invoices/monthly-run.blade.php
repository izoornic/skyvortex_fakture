<?php

use App\Actions\Contracts\GenerateContractDrafts;
use App\Actions\Invoices\IssueInvoices;
use App\Models\Contract;
use App\Models\Invoice;
use App\Support\CurrentCompany;
use App\Support\PeriodLabel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * Monthly invoicing in one screen: what the contracts owe, then what is ready to
 * go out. Nothing here happens on its own — the drafts are made when somebody
 * asks, and issued when somebody confirms.
 */
new class extends Component {
    #[Url(as: 'dan', except: '')]
    public string $runOn = '';

    /** @var list<int> */
    public array $selected = [];

    public bool $selectAll = false;

    /** @var array{created: int, skipped: list<string>}|null */
    public ?array $generated = null;

    /** @var array{issued: int, failed: list<string>}|null */
    public ?array $issuedResult = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Invoice::class);
        Gate::authorize('viewAny', Contract::class);

        $this->runOn = $this->runOn ?: now()->toDateString();
    }

    public function updatedRunOn(): void
    {
        $this->generated = null;
        $this->issuedResult = null;
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value ? $this->draftIds() : [];
    }

    public function generate(GenerateContractDrafts $drafts): void
    {
        $company = $this->company();

        Gate::authorize('create', Invoice::class);

        $result = $drafts->handle($company, $this->runDate());

        $this->generated = [
            'created' => $result['created']->count(),
            'skipped' => $result['skipped']
                ->map(fn (array $row) => $row['contract']->name.' — '.$row['reason'])
                ->all(),
        ];

        $this->issuedResult = null;
        $this->selected = [];
        $this->selectAll = false;
    }

    public function issueSelected(IssueInvoices $issuer): void
    {
        if ($this->selected === []) {
            return;
        }

        $invoices = Invoice::query()
            ->drafts()
            ->whereIn('id', $this->selected)
            ->with('items')
            ->get()
            ->filter(fn (Invoice $invoice) => Gate::allows('issue', $invoice));

        $result = $issuer->handle($invoices);

        $this->issuedResult = [
            'issued' => $result['issued']->count(),
            'failed' => $result['failed']
                ->map(fn (array $row) => $row['invoice']->partner->name.' — '.$row['reason'])
                ->all(),
        ];

        $this->generated = null;
        $this->selected = [];
        $this->selectAll = false;
    }

    public function deleteDraft(int $id): void
    {
        $invoice = Invoice::findOrFail($id);

        Gate::authorize('delete', $invoice);

        $invoice->delete();

        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    private function runDate(): Carbon
    {
        return Carbon::parse($this->runOn ?: now()->toDateString())->startOfDay();
    }

    private function company(): \App\Models\Company
    {
        $company = app(CurrentCompany::class)->get();

        abort_if($company === null, 409, 'Nije izabrano pravno lice.');

        return $company;
    }

    /**
     * @return list<int>
     */
    private function draftIds(): array
    {
        return $this->draftQuery()->pluck('id')->all();
    }

    private function draftQuery()
    {
        return Invoice::query()
            ->drafts()
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->orderBy('id');
    }

    public function with(): array
    {
        $company = app(CurrentCompany::class)->get();

        $drafts = $company
            ? $this->draftQuery()->with(['partner', 'contract', 'items'])->get()
            : collect();

        return [
            'company' => $company,
            'runDate' => $this->runDate(),
            'due' => $company ? app(GenerateContractDrafts::class)->preview($company, $this->runDate()) : collect(),
            'drafts' => $drafts,
            'draftsTotal' => $drafts->sum(fn (Invoice $invoice) => (float) $invoice->total_rsd),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Mesečno fakturisanje</flux:heading>
            <flux:subheading>{{ $company?->displayName() }}</flux:subheading>
        </div>

        <flux:button icon="document-duplicate" :href="route('contracts.index')" wire:navigate>Ugovori</flux:button>
    </div>

    @if (! $company)
        <flux:callout icon="building-office" class="mt-6">
            <flux:callout.heading>Nije izabrano pravno lice</flux:callout.heading>
            <flux:callout.text>Izaberi pravno lice u bočnoj traci.</flux:callout.text>
        </flux:callout>
    @else
        {{-- 1. Šta ugovori duguju --}}
        <div class="mt-6 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <flux:heading size="lg">Na dan {{ $runDate->format('d.m.Y.') }}</flux:heading>
                    <flux:text size="sm">
                        Ugovori koji za taj dan duguju nacrt. Ako je dan promašen, pomeri datum unazad.
                    </flux:text>
                </div>

                <div class="flex items-end gap-2">
                    <flux:input type="date" wire:model.live="runOn" label="Datum obračuna" class="max-w-44" />

                    <flux:button variant="primary" icon="document-plus" wire:click="generate"
                        :disabled="$due->where('blocked', null)->isEmpty()">
                        Napravi nacrte ({{ $due->where('blocked', null)->count() }})
                    </flux:button>
                </div>
            </div>

            @if ($generated)
                <flux:callout icon="check-circle" color="lime" class="mt-4">
                    <flux:callout.text>Napravljeno nacrta: <strong>{{ $generated['created'] }}</strong>.</flux:callout.text>
                    @foreach ($generated['skipped'] as $skipped)
                        <flux:callout.text>Preskočeno: {{ $skipped }}</flux:callout.text>
                    @endforeach
                </flux:callout>
            @endif

            @if ($due->isEmpty())
                <flux:text class="mt-4">
                    Nijedan ugovor ne duguje nacrt na ovaj dan — ili su već napravljeni.
                </flux:text>
            @else
                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>Ugovor</flux:table.column>
                        <flux:table.column>Partner</flux:table.column>
                        <flux:table.column>Period</flux:table.column>
                        <flux:table.column>Promet</flux:table.column>
                        <flux:table.column align="end">Iznos</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($due as $row)
                            <flux:table.row :key="'due-'.$row['contract']->id">
                                <flux:table.cell>{{ $row['contract']->name }}</flux:table.cell>
                                <flux:table.cell>{{ $row['contract']->partner->name }}</flux:table.cell>
                                <flux:table.cell>{{ \App\Support\PeriodLabel::forDate($row['period']) }}</flux:table.cell>
                                <flux:table.cell>{{ $row['supply_date']->format('d.m.Y.') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($row['blocked'])
                                        <flux:badge size="sm" color="amber">{{ $row['blocked'] }}</flux:badge>
                                    @else
                                        {{ number_format($row['total'], 2, ',', '.') }} {{ $row['contract']->currency }}
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        {{-- 2. Šta čeka na izdavanje --}}
        <div class="mt-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <flux:heading size="lg">Nacrti ({{ $drafts->count() }})</flux:heading>
                    <flux:text size="sm">Pregledaj, ispravi ako treba, pa izdaj.</flux:text>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-sm text-zinc-500">
                        Ukupno: <strong class="text-zinc-900 dark:text-white">{{ number_format($draftsTotal, 2, ',', '.') }} RSD</strong>
                    </span>

                    <flux:button variant="primary" icon="paper-airplane" wire:click="issueSelected"
                        wire:confirm="Izdati izabrane dokumente? Izdati dokument se više ne menja."
                        :disabled="count($selected) === 0">
                        Izdaj izabrane ({{ count($selected) }})
                    </flux:button>
                </div>
            </div>

            @if ($issuedResult)
                <flux:callout icon="check-circle" :color="$issuedResult['failed'] === [] ? 'lime' : 'amber'" class="mt-4">
                    <flux:callout.text>Izdato dokumenata: <strong>{{ $issuedResult['issued'] }}</strong>.</flux:callout.text>
                    @foreach ($issuedResult['failed'] as $failed)
                        <flux:callout.text>Nije izdato: {{ $failed }}</flux:callout.text>
                    @endforeach
                </flux:callout>
            @endif

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>
                        <flux:checkbox wire:model.live="selectAll" />
                    </flux:table.column>
                    <flux:table.column>Partner</flux:table.column>
                    <flux:table.column>Period</flux:table.column>
                    <flux:table.column>Poreklo</flux:table.column>
                    <flux:table.column align="end">Iznos</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($drafts as $draft)
                        <flux:table.row :key="'draft-'.$draft->id">
                            <flux:table.cell>
                                <flux:checkbox wire:model.live="selected" value="{{ $draft->id }}" />
                            </flux:table.cell>

                            <flux:table.cell>
                                <a href="{{ route('invoices.edit', $draft) }}" wire:navigate class="font-medium hover:underline">
                                    {{ $draft->partner->name }}
                                </a>
                                @if ($draft->items->isEmpty())
                                    <flux:badge size="sm" color="red" class="ms-2">bez stavki</flux:badge>
                                @endif
                                @if ($draft->isForeignCurrency())
                                    <flux:badge size="sm" color="amber" class="ms-2">
                                        {{ $draft->currency }} · kurs {{ rtrim(rtrim($draft->exchange_rate, '0'), '.') }}
                                    </flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>{{ $draft->periodLabel() }}</flux:table.cell>

                            <flux:table.cell>
                                @if ($draft->contract)
                                    <span class="text-zinc-500">{{ $draft->contract->name }}</span>
                                @else
                                    <span class="text-zinc-400">ručno</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                {{ number_format($draft->total, 2, ',', '.') }} {{ $draft->currency }}
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square"
                                        :href="route('invoices.edit', $draft)" wire:navigate />
                                    <flux:button size="sm" variant="ghost" icon="trash"
                                        wire:click="deleteDraft({{ $draft->id }})"
                                        wire:confirm="Obrisati nacrt?" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500">
                                Nema nacrta koji čekaju.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</section>
