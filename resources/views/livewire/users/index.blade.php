<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->withCount('companies')
                ->orderBy('name')
                ->paginate(config('global.paginate')),
        ];
    }
}; ?>

<section class="w-full">
    <div>
        <flux:heading size="xl">Korisnici</flux:heading>
        <flux:subheading>Uloge i dodela pravnih lica</flux:subheading>
    </div>

    <flux:table :paginate="$users" class="mt-6">
        <flux:table.columns>
            <flux:table.column>Ime</flux:table.column>
            <flux:table.column>E-pošta</flux:table.column>
            <flux:table.column>Uloga</flux:table.column>
            <flux:table.column>Pravna lica</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$user->isAdmin() ? 'violet' : 'zinc'">
                            {{ $user->role->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $user->isAdmin() ? 'sva' : $user->companies_count }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                            :href="route('users.edit', $user)" wire:navigate>
                            Izmeni
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
