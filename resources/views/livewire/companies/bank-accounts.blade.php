<?php

use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public Company $company;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $bank_name = '';
    public string $account_number = '';
    public string $iban = '';
    public string $swift = '';
    public string $currency = 'RSD';
    public bool $is_primary = false;

    public function with(): array
    {
        return [
            'accounts' => $this->company->bankAccounts()->get(),
            'canManage' => auth()->user()->can('create', BankAccount::class),
        ];
    }

    public function add(): void
    {
        Gate::authorize('create', BankAccount::class);

        $this->resetForm();
        $this->is_primary = $this->company->bankAccounts()->count() === 0;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $account = $this->account($id);

        Gate::authorize('update', $account);

        $this->editingId = $account->id;
        $this->bank_name = $account->bank_name;
        $this->account_number = $account->account_number;
        $this->iban = (string) $account->iban;
        $this->swift = (string) $account->swift;
        $this->currency = $account->currency;
        $this->is_primary = $account->is_primary;
        $this->showForm = true;
    }

    public function save(): void
    {
        $account = $this->editingId ? $this->account($this->editingId) : null;

        Gate::authorize($account ? 'update' : 'create', $account ?? BankAccount::class);

        $data = $this->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => [
                'required', 'string', 'max:25',
                Rule::unique('bank_accounts', 'account_number')
                    ->where('company_id', $this->company->id)
                    ->ignore($account),
            ],
            'iban' => ['nullable', 'string', 'max:34'],
            'swift' => ['nullable', 'string', 'max:11'],
            'currency' => ['required', 'string', 'size:3'],
            'is_primary' => ['boolean'],
        ]);

        $data['iban'] = $data['iban'] ?: null;
        $data['swift'] = $data['swift'] ?: null;

        DB::transaction(function () use ($account, $data) {
            $saved = $account
                ? tap($account)->update($data)
                : $this->company->bankAccounts()->create($data);

            // Exactly one primary account per company.
            if ($saved->is_primary) {
                $this->company->bankAccounts()
                    ->whereKeyNot($saved->id)
                    ->update(['is_primary' => false]);
            }
        });

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $account = $this->account($id);

        Gate::authorize('delete', $account);

        $account->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function account(int $id): BankAccount
    {
        return $this->company->bankAccounts()->whereKey($id)->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'bank_name', 'account_number', 'iban', 'swift', 'is_primary']);
        $this->currency = $this->company->default_currency;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="lg">Bankovni računi</flux:heading>
            <flux:subheading>Račun označen kao primarni ide na fakturu</flux:subheading>
        </div>

        @if ($canManage && ! $showForm)
            <flux:button size="sm" icon="plus" wire:click="add">Dodaj račun</flux:button>
        @endif
    </div>

    @if ($accounts->isEmpty() && ! $showForm)
        <flux:callout icon="banknotes" class="mt-4">
            <flux:callout.text>Nema unetih računa.</flux:callout.text>
        </flux:callout>
    @elseif ($accounts->isNotEmpty())
        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Banka</flux:table.column>
                <flux:table.column>Broj računa</flux:table.column>
                <flux:table.column>Valuta</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($accounts as $account)
                    <flux:table.row :key="$account->id">
                        <flux:table.cell>
                            {{ $account->bank_name }}
                            @if ($account->is_primary)
                                <flux:badge size="sm" color="lime">primarni</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $account->formattedAccountNumber() }}</flux:table.cell>
                        <flux:table.cell>{{ $account->currency }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($canManage)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $account->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="delete({{ $account->id }})"
                                    wire:confirm="Obrisati račun {{ $account->formattedAccountNumber() }}?" />
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
                <flux:input wire:model="bank_name" label="Banka" required />
                <flux:input wire:model="account_number" label="Broj računa" description="18 cifara, bez crtica" required />
                <flux:input wire:model="iban" label="IBAN" />
                <flux:input wire:model="swift" label="SWIFT" />
                <flux:input wire:model="currency" label="Valuta" maxlength="3" required />
            </div>

            <flux:switch wire:model="is_primary" label="Primarni račun" />

            <div class="flex items-center gap-3">
                <flux:button type="submit" size="sm" variant="primary">Sačuvaj račun</flux:button>
                <flux:button type="button" size="sm" variant="ghost" wire:click="cancel">Odustani</flux:button>
            </div>
        </form>
    @endif
</div>
