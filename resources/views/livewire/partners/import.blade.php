<?php

use App\Actions\ImportPartners;
use App\Models\Partner;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $file;

    public bool $updateExisting = true;

    /** @var array{parsed: int, created: int, updated: int, errors: list<array{row: int, name: string, message: string}>}|null */
    public ?array $result = null;

    public function mount(): void
    {
        Gate::authorize('import', Partner::class);
    }

    public function import(ImportPartners $importer): void
    {
        Gate::authorize('import', Partner::class);

        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.mimes' => 'Očekuje se CSV fajl.',
            'file.max' => 'Fajl je veći od 5 MB.',
        ]);

        $company = app(CurrentCompany::class)->get();

        abort_if($company === null, 409, 'Nije izabrano pravno lice.');

        $this->result = $importer->handle(
            $this->file->getRealPath(),
            $company,
            $this->updateExisting,
        );

        $this->reset('file');
    }

    public function with(): array
    {
        return [
            'company' => app(CurrentCompany::class)->get(),
            'headers' => ImportPartners::templateHeaders(),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('partners.index')" wire:navigate />
        <div>
            <flux:heading size="xl">Uvoz partnera</flux:heading>
            <flux:subheading>
                CSV fajl{{ $company ? ' — uvozi se u '.$company->displayName() : '' }}
            </flux:subheading>
        </div>
    </div>

    @if (! $company)
        <flux:callout icon="building-office" class="mt-6">
            <flux:callout.heading>Nije izabrano pravno lice</flux:callout.heading>
            <flux:callout.text>Izaberi pravno lice u bočnoj traci pre uvoza.</flux:callout.text>
        </flux:callout>
    @else
        <div class="mt-6 max-w-3xl space-y-6">
            <flux:callout icon="information-circle">
                <flux:callout.heading>Očekivane kolone</flux:callout.heading>
                <flux:callout.text>
                    Prvi red mora biti zaglavlje. Obavezna je samo kolona <strong>naziv</strong>,
                    ostale su opcione i mogu biti u bilo kom redosledu. Razdvajač može biti
                    tačka-zarez ili zarez.
                    <span class="mt-2 block font-mono text-xs">{{ implode(' · ', $headers) }}</span>
                </flux:callout.text>
            </flux:callout>

            <form wire:submit="import" class="space-y-4">
                <flux:input type="file" wire:model="file" label="CSV fajl" accept=".csv,text/csv" required />

                <flux:switch wire:model="updateExisting" label="Ažuriraj postojeće"
                    description="Partner se prepoznaje po PIB-u unutar ovog pravnog lica" />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray">
                        <span wire:loading.remove wire:target="import">Uvezi</span>
                        <span wire:loading wire:target="import">Uvozim…</span>
                    </flux:button>
                </div>
            </form>

            @if ($result)
                <flux:separator />

                <div>
                    <flux:heading size="lg">Rezultat uvoza</flux:heading>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <flux:badge color="zinc">pročitano: {{ $result['parsed'] }}</flux:badge>
                        <flux:badge color="lime">novih: {{ $result['created'] }}</flux:badge>
                        <flux:badge color="blue">ažurirano: {{ $result['updated'] }}</flux:badge>
                        <flux:badge :color="count($result['errors']) ? 'red' : 'zinc'">
                            preskočeno: {{ count($result['errors']) }}
                        </flux:badge>
                    </div>

                    @if ($result['errors'])
                        <flux:table class="mt-4">
                            <flux:table.columns>
                                <flux:table.column>Red</flux:table.column>
                                <flux:table.column>Naziv</flux:table.column>
                                <flux:table.column>Razlog</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($result['errors'] as $error)
                                    <flux:table.row>
                                        <flux:table.cell variant="strong">{{ $error['row'] }}</flux:table.cell>
                                        <flux:table.cell>{{ $error['name'] ?: '—' }}</flux:table.cell>
                                        <flux:table.cell class="whitespace-normal">{{ $error['message'] }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif

                    <flux:button class="mt-4" :href="route('partners.index')" wire:navigate>
                        Nazad na partnere
                    </flux:button>
                </div>
            @endif
        </div>
    @endif
</section>
