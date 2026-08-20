<?php

use App\Enums\VatCategory;
use App\Models\VatExemptionReason;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $code = '';
    public string $vat_category = VatCategory::Exempt->value;
    public string $description = '';
    public string $legal_basis = '';
    public bool $is_active = true;
    public int $sort_order = 0;

    public function with(): array
    {
        return [
            'reasons' => VatExemptionReason::query()->ordered()->get(),
            'categories' => VatCategory::options(),
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

        $reason = VatExemptionReason::findOrFail($id);

        $this->editingId = $reason->id;
        $this->code = $reason->code;
        $this->vat_category = $reason->vat_category->value;
        $this->description = $reason->description;
        $this->legal_basis = (string) $reason->legal_basis;
        $this->is_active = $reason->is_active;
        $this->sort_order = $reason->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage-codebooks');

        $reason = $this->editingId ? VatExemptionReason::findOrFail($this->editingId) : null;

        $data = $this->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('vat_exemption_reasons', 'code')->ignore($reason)],
            'vat_category' => ['required', Rule::enum(VatCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data['legal_basis'] = $data['legal_basis'] ?: null;

        $reason ? $reason->update($data) : VatExemptionReason::create($data);

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
        $this->reset(['editingId', 'code', 'vat_category', 'description', 'legal_basis', 'is_active', 'sort_order']);
        $this->vat_category = VatCategory::Exempt->value;
        $this->resetValidation();
    }
}; ?>

<section class="w-full">
    @include('partials.codebook-heading')

    <div class="mt-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <flux:heading size="lg">Osnovi oslobođenja od PDV-a</flux:heading>
                <flux:subheading>Razlog koji ide na fakturu kada se PDV ne obračunava</flux:subheading>
            </div>

            @if ($canManage && ! $showForm)
                <flux:button size="sm" icon="plus" wire:click="add">Dodaj osnov</flux:button>
            @endif
        </div>

        @if ($reasons->isEmpty())
            <flux:callout icon="exclamation-triangle" color="amber" class="mt-4">
                <flux:callout.heading>Šifarnik je namerno prazan</flux:callout.heading>
                <flux:callout.text>
                    Šifre i formulacije osnova oslobođenja propisuje poreska regulativa i
                    specifikacija SEF-a, i menjaju se kroz vreme. Nisu unapred popunjene da
                    se ne bi na fakturama našao pogrešan pravni osnov.
                    <span class="mt-2 block">
                        Unesi ih po važećoj regulativi, uz knjigovođu ili poreskog savetnika.
                        Popunjavanje je obavezno pre nego što se u M2 krene sa izdavanjem
                        faktura bez PDV-a.
                    </span>
                </flux:callout.text>
            </flux:callout>
        @else
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Šifra</flux:table.column>
                    <flux:table.column>PDV kategorija</flux:table.column>
                    <flux:table.column>Opis</flux:table.column>
                    <flux:table.column>Pravni osnov</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($reasons as $reason)
                        <flux:table.row :key="$reason->id">
                            <flux:table.cell variant="strong">
                                {{ $reason->code }}
                                @unless ($reason->is_active)
                                    <flux:badge size="sm" color="zinc">neaktivan</flux:badge>
                                @endunless
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">{{ $reason->vat_category->value }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-normal">{{ $reason->description }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal">{{ $reason->legal_basis ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($canManage)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $reason->id }})" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        @if ($showForm)
            <form wire:submit="save" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="code" label="Šifra" description="Kako je traži SEF, npr. PDV-RS-33" maxlength="30" required />

                    <flux:select wire:model="vat_category" label="PDV kategorija" required>
                        @foreach ($categories as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="sm:col-span-2">
                        <flux:input wire:model="description" label="Opis" description="Tekst koji se ispisuje na fakturi" required />
                    </div>

                    <div class="sm:col-span-2">
                        <flux:input wire:model="legal_basis" label="Pravni osnov" description="Član i zakon na koji se poziva" />
                    </div>

                    <flux:input wire:model="sort_order" label="Redosled" type="number" min="0" />
                </div>

                <flux:switch wire:model="is_active" label="Aktivan" />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" size="sm" variant="primary">Sačuvaj</flux:button>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="cancel">Odustani</flux:button>
                </div>
            </form>
        @endif
    </div>
</section>
