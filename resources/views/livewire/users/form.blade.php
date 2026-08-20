<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Volt\Component;

new class extends Component {
    public ?User $editing = null;

    public string $name = '';
    public string $email = '';
    public string $role = UserRole::Bookkeeper->value;
    public string $password = '';

    /** @var list<int> */
    public array $companyIds = [];

    public function mount(?User $user = null): void
    {
        $this->editing = $user?->exists ? $user : null;

        if ($this->editing) {
            Gate::authorize('update', $this->editing);

            $this->name = $this->editing->name;
            $this->email = $this->editing->email;
            $this->role = $this->editing->role->value;
            $this->companyIds = $this->editing->companies()->pluck('companies.id')->all();

            return;
        }

        Gate::authorize('create', User::class);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editing)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$this->editing ? 'nullable' : 'required', 'string', Password::defaults()],
            'companyIds' => ['array'],
            'companyIds.*' => ['integer', 'exists:companies,id'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ];

        if ($data['password'] !== '') {
            $attributes['password'] = $data['password'];
        }

        $user = $this->editing
            ? tap($this->editing)->update($attributes)
            : User::create($attributes);

        // Administrators reach every company, so assignments are meaningless
        // for them and would only go stale.
        $user->companies()->sync(
            $user->role->seesAllCompanies() ? [] : $data['companyIds']
        );

        $this->redirect(route('users.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'roles' => UserRole::options(),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name', 'short_name']),
            'isAdminRole' => UserRole::from($this->role)->seesAllCompanies(),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('users.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $editing ? $editing->name : 'Novi korisnik' }}</flux:heading>
            <flux:subheading>Uloga određuje koja pravna lica korisnik vidi</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="mt-6 max-w-2xl space-y-6">
        <flux:input wire:model="name" label="Ime i prezime" required autofocus />
        <flux:input wire:model="email" label="E-pošta" type="email" required />

        <flux:input wire:model="password" label="Lozinka" type="password"
            :description="$editing ? 'Ostavi prazno da se lozinka ne menja' : null"
            :required="! $editing" />

        <flux:select wire:model.live="role" label="Uloga" required>
            @foreach ($roles as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:separator />

        <div>
            <flux:heading size="lg">Dodeljena pravna lica</flux:heading>

            @if ($isAdminRole)
                <flux:callout icon="shield-check" class="mt-3">
                    <flux:callout.text>
                        Administrator vidi sva pravna lica, pa se dodela ne primenjuje.
                    </flux:callout.text>
                </flux:callout>
            @elseif ($companies->isEmpty())
                <flux:callout icon="building-office" class="mt-3">
                    <flux:callout.text>Nema unetih pravnih lica koja bi se dodelila.</flux:callout.text>
                </flux:callout>
            @else
                <flux:checkbox.group wire:model="companyIds" class="mt-3 space-y-2">
                    @foreach ($companies as $company)
                        <flux:checkbox :value="$company->id" :label="$company->displayName()" />
                    @endforeach
                </flux:checkbox.group>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Sačuvaj</flux:button>
            <flux:button variant="ghost" :href="route('users.index')" wire:navigate>Odustani</flux:button>
        </div>
    </form>
</section>
