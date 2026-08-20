<?php

use App\Models\Currency;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $code = '';
    public string $name = '';
    public string $symbol = '';
    public int $decimal_places = 2;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function with(): array
    {
        return [
            'currencies' => Currency::query()->ordered()->get(),
            'canManage' => Gate::allows('manage-codebooks'),
        ];
    }

    public function add(): void
    {
        Gate::authorize('manage-codebooks');

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-codebooks');

        $currency = Currency::findOrFail($id);

        $this->editingId = $currency->id;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->symbol = (string) $currency->symbol;
        $this->decimal_places = $currency->decimal_places;
        $this->is_active = $currency->is_active;
        $this->sort_order = $currency->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage-codebooks');

        $currency = $this->editingId ? Currency::findOrFail($this->editingId) : null;

        $data = $this->validate([
            'code' => ['required', 'string', 'size:3', Rule::unique('currencies', 'code')->ignore($currency)],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data['code'] = mb_strtoupper($data['code']);
        $data['symbol'] = $data['symbol'] ?: null;

        $currency ? $currency->update($data) : Currency::create($data);

        $this->resetForm();
        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'symbol', 'decimal_places', 'is_active', 'sort_order']);
        $this->resetValidation();
    }
}; ?>

<section class="w-full">
    @include('partials.codebook-heading')

    <div class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <flux:heading size="lg">Valute</flux:heading>
                <flux:subheading>Valute u kojima se izdaju fakture</flux:subheading>
            </div>

            @if ($canManage && ! $showForm)
                <flux:button size="sm" icon="plus" wire:click="add">Dodaj valutu</flux:button>
            @endif
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Šifra</flux:table.column>
                <flux:table.column>Naziv</flux:table.column>
                <flux:table.column>Simbol</flux:table.column>
                <flux:table.column>Decimala</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($currencies as $currency)
                    <flux:table.row :key="$currency->id">
                        <flux:table.cell variant="strong">
                            {{ $currency->code }}
                            @unless ($currency->is_active)
                                <flux:badge size="sm" color="zinc">neaktivna</flux:badge>
                            @endunless
                        </flux:table.cell>
                        <flux:table.cell>{{ $currency->name }}</flux:table.cell>
                        <flux:table.cell>{{ $currency->symbol ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $currency->decimal_places }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($canManage)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $currency->id }})" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($showForm)
            <form wire:submit="save" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="code" label="Šifra" description="ISO 4217, npr. RSD" maxlength="3" required />
                    <flux:input wire:model="name" label="Naziv" required />
                    <flux:input wire:model="symbol" label="Simbol" />
                    <flux:input wire:model="decimal_places" label="Broj decimala" type="number" min="0" max="4" required />
                    <flux:input wire:model="sort_order" label="Redosled" type="number" min="0" />
                </div>

                <flux:switch wire:model="is_active" label="Aktivna" />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" size="sm" variant="primary">Sačuvaj</flux:button>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="cancel">Odustani</flux:button>
                </div>
            </form>
        @endif
    </div>
</section>
