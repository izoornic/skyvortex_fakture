<?php

use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $code = '';
    public string $name = '';
    public string $symbol = '';
    public bool $is_active = true;
    public int $sort_order = 0;

    public function with(): array
    {
        return [
            'units' => UnitOfMeasure::query()->ordered()->get(),
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

        $unit = UnitOfMeasure::findOrFail($id);

        $this->editingId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->symbol = (string) $unit->symbol;
        $this->is_active = $unit->is_active;
        $this->sort_order = $unit->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage-codebooks');

        $unit = $this->editingId ? UnitOfMeasure::findOrFail($this->editingId) : null;

        $data = $this->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('units_of_measure', 'code')->ignore($unit)],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data['code'] = mb_strtoupper($data['code']);
        $data['symbol'] = $data['symbol'] ?: null;

        $unit ? $unit->update($data) : UnitOfMeasure::create($data);

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
        $this->reset(['editingId', 'code', 'name', 'symbol', 'is_active', 'sort_order']);
        $this->resetValidation();
    }
}; ?>

<section class="w-full">
    @include('partials.codebook-heading')

    <div class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <flux:heading size="lg">Jedinice mere</flux:heading>
                <flux:subheading>Šifre po UN/ECE Rec 20 — traži ih UBL u fazi 2</flux:subheading>
            </div>

            @if ($canManage && ! $showForm)
                <flux:button size="sm" icon="plus" wire:click="add">Dodaj jedinicu</flux:button>
            @endif
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Šifra</flux:table.column>
                <flux:table.column>Naziv</flux:table.column>
                <flux:table.column>Oznaka</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($units as $unit)
                    <flux:table.row :key="$unit->id">
                        <flux:table.cell variant="strong">
                            {{ $unit->code }}
                            @unless ($unit->is_active)
                                <flux:badge size="sm" color="zinc">neaktivna</flux:badge>
                            @endunless
                        </flux:table.cell>
                        <flux:table.cell>{{ $unit->name }}</flux:table.cell>
                        <flux:table.cell>{{ $unit->symbol ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($canManage)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $unit->id }})" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($showForm)
            <form wire:submit="save" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="code" label="Šifra" description="UN/ECE Rec 20, npr. H87" maxlength="10" required />
                    <flux:input wire:model="name" label="Naziv" required />
                    <flux:input wire:model="symbol" label="Oznaka" description="Prikazuje se na fakturi" />
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
