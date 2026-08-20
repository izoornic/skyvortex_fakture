<?php

use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * Companies the signed in user may work with.
     */
    public function with(): array
    {
        return [
            'companies' => $this->companies(),
            'current' => app(CurrentCompany::class)->get(),
        ];
    }

    public function select(int $companyId): void
    {
        $company = Auth::user()->accessibleCompanies()->whereKey($companyId)->first();

        if (! $company) {
            return;
        }

        app(CurrentCompany::class)->set($company);

        // Reload the page so every scoped query re-runs for the new company.
        $this->redirect(url()->previous() ?: route('dashboard'), navigate: true);
    }

    private function companies(): Collection
    {
        return Auth::user()
            ->accessibleCompanies()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);
    }
}; ?>

<div class="w-full">
    @if ($companies->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            Nema dodeljenih pravnih lica
        </div>
    @else
        <flux:dropdown position="bottom" align="start" class="w-full">
            <flux:button variant="ghost" class="w-full justify-between!" icon-trailing="chevrons-up-down">
                <span class="truncate text-left">{{ $current?->displayName() ?? 'Izaberi pravno lice' }}</span>
            </flux:button>

            <flux:menu class="w-[260px]">
                <flux:menu.group heading="Pravno lice">
                    @foreach ($companies as $company)
                        <flux:menu.item
                            wire:click="select({{ $company->id }})"
                            :icon="$current?->is($company) ? 'check' : null"
                        >
                            {{ $company->displayName() }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu.group>
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
