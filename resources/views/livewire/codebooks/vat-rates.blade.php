<?php

use App\Models\VatRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';
    public string $rate = '20.00';
    public string $valid_from = '';
    public string $valid_to = '';
    public bool $is_default = false;
    public int $sort_order = 0;

    public function with(): array
    {
        return [
            'rates' => VatRate::query()->ordered()->get(),
            'canManage' => Gate::allows('manage-codebooks'),
        ];
    }

    public function add(): void
    {
        Gate::authorize('manage-codebooks');

        $this->resetForm();
        $this->valid_from = now()->toDateString();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-codebooks');

        $rate = VatRate::findOrFail($id);

        $this->editingId = $rate->id;
        $this->name = $rate->name;
        $this->rate = (string) $rate->rate;
        $this->valid_from = $rate->valid_from->toDateString();
        $this->valid_to = $rate->valid_to?->toDateString() ?? '';
        $this->is_default = $rate->is_default;
        $this->sort_order = $rate->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage-codebooks');

        $rate = $this->editingId ? VatRate::findOrFail($this->editingId) : null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ], [
            'valid_to.after_or_equal' => 'Kraj važenja ne može biti pre početka.',
        ]);

        $data['valid_to'] = $data['valid_to'] ?: null;

        DB::transaction(function () use ($rate, $data) {
            $saved = $rate ? tap($rate)->update($data) : VatRate::create($data);

            // Exactly one default rate.
            if ($saved->is_default) {
                VatRate::query()->whereKeyNot($saved->id)->update(['is_default' => false]);
            }
        });

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
        $this->reset(['editingId', 'name', 'rate', 'valid_from', 'valid_to', 'is_default', 'sort_order']);
        $this->resetValidation();
    }
}; ?>

<section class="w-full">
    @include('partials.codebook-heading')

    <div class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <flux:heading size="lg">PDV stope</flux:heading>
                <flux:subheading>Stopa važi po datumu prometa, ne po datumu izdavanja</flux:subheading>
            </div>

            @if ($canManage && ! $showForm)
                <flux:button size="sm" icon="plus" wire:click="add">Dodaj stopu</flux:button>
            @endif
        </div>

        <flux:callout icon="calendar-days" class="mt-4">
            <flux:callout.text>
                Stopa se ne menja izmenom postojećeg reda — postojećoj se upiše kraj važenja,
                pa se doda nova. Tako stare fakture zadrže stopu koja je tada važila.
            </flux:callout.text>
        </flux:callout>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Naziv</flux:table.column>
                <flux:table.column>Stopa</flux:table.column>
                <flux:table.column>Važi od</flux:table.column>
                <flux:table.column>Važi do</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($rates as $rate)
                    <flux:table.row :key="$rate->id">
                        <flux:table.cell>
                            {{ $rate->name }}
                            @if ($rate->is_default)
                                <flux:badge size="sm" color="lime">podrazumevana</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $rate->rate }}%</flux:table.cell>
                        <flux:table.cell>{{ $rate->valid_from->format('d.m.Y.') }}</flux:table.cell>
                        <flux:table.cell>{{ $rate->valid_to?->format('d.m.Y.') ?? 'i dalje' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($canManage)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $rate->id }})" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if ($showForm)
            <form wire:submit="save" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" label="Naziv" required />
                    <flux:input wire:model="rate" label="Stopa (%)" type="number" step="0.01" min="0" max="100" required />
                    <flux:input wire:model="valid_from" label="Važi od" type="date" required />
                    <flux:input wire:model="valid_to" label="Važi do" type="date" description="Prazno znači i dalje važi" />
                    <flux:input wire:model="sort_order" label="Redosled" type="number" min="0" />
                </div>

                <flux:switch wire:model="is_default" label="Podrazumevana stopa" />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" size="sm" variant="primary">Sačuvaj</flux:button>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="cancel">Odustani</flux:button>
                </div>
            </form>
        @endif
    </div>
</section>
