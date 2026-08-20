<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceNumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?Company $company = null;

    public string $name = '';
    public string $short_name = '';
    public string $pib = '';
    public string $registration_number = '';
    public string $address = '';
    public string $city = '';
    public string $postal_code = '';
    public string $country_code = 'RS';
    public string $activity_code = '';
    public string $jbkjs = '';
    public bool $in_vat_system = true;
    public string $vat_registered_at = '';
    public string $default_currency = 'RSD';
    public string $email = '';
    public string $phone = '';
    public string $website = '';
    public bool $is_active = true;

    /**
     * Documents issued before the application existed; numbering continues
     * from here. Only asked for when the company is created.
     */
    public int $last_invoice_number = 0;

    public function mount(?Company $company = null): void
    {
        $this->company = $company?->exists ? $company : null;

        if ($this->company) {
            Gate::authorize('update', $this->company);

            // Nullable columns are cast to '' here: the form properties are
            // typed string, and fill() would blow up on a null.
            foreach ([
                'name', 'short_name', 'pib', 'registration_number', 'address', 'city',
                'postal_code', 'country_code', 'activity_code', 'jbkjs',
                'default_currency', 'email', 'phone', 'website',
            ] as $field) {
                $this->{$field} = (string) $this->company->{$field};
            }

            $this->in_vat_system = $this->company->in_vat_system;
            $this->is_active = $this->company->is_active;
            $this->vat_registered_at = $this->company->vat_registered_at?->toDateString() ?? '';

            return;
        }

        Gate::authorize('create', Company::class);
    }

    public function save(): void
    {
        $data = $this->validate($this->rules());

        $data['vat_registered_at'] = $data['vat_registered_at'] ?: null;

        foreach (['short_name', 'postal_code', 'activity_code', 'jbkjs', 'email', 'phone', 'website'] as $optional) {
            $data[$optional] = $data[$optional] ?: null;
        }

        $lastNumber = (int) ($data['last_invoice_number'] ?? 0);
        unset($data['last_invoice_number']);

        if ($this->company) {
            $this->company->update($data);
        } else {
            DB::transaction(function () use ($data, $lastNumber) {
                $this->company = Company::create($data);

                InvoiceNumberSequence::create([
                    'company_id' => $this->company->id,
                    'type' => DocumentType::Invoice,
                    'year' => now()->year,
                    'last_number' => $lastNumber,
                ]);
            });
        }

        $this->redirect(route('companies.edit', $this->company), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'pib' => ['required', 'digits:9', Rule::unique('companies', 'pib')->ignore($this->company)],
            'registration_number' => ['required', 'digits:8'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country_code' => ['required', 'string', 'size:2'],
            'activity_code' => ['nullable', 'string', 'max:10'],
            'jbkjs' => ['nullable', 'digits:5'],
            'in_vat_system' => ['boolean'],
            'vat_registered_at' => ['nullable', 'date'],
            'default_currency' => ['required', 'string', 'size:3'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_active' => ['boolean'],
        ];

        if (! $this->company) {
            $rules['last_invoice_number'] = ['required', 'integer', 'min:0', 'max:999999'];
        }

        return $rules;
    }

    public function with(): array
    {
        return [
            'currencies' => ['RSD', 'EUR', 'USD', 'CHF', 'GBP'],
            'nextNumberPreview' => sprintf('%d-%s', now()->year, str_pad((string) ($this->last_invoice_number + 1), 4, '0', STR_PAD_LEFT)),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('companies.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $company ? $company->name : 'Novo pravno lice' }}</flux:heading>
            <flux:subheading>{{ $company ? 'Izmena podataka izdavaoca' : 'Podaci izdavaoca faktura' }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="mt-6 max-w-3xl space-y-8">
        <flux:fieldset>
            <flux:legend>Identifikacija</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:input wire:model="name" label="Pun naziv" required autofocus />
                </div>
                <flux:input wire:model="short_name" label="Skraćeni naziv" description="Prikazuje se u prekidaču i listama" />
                <flux:input wire:model="pib" label="PIB" required inputmode="numeric" maxlength="9" />
                <flux:input wire:model="registration_number" label="Matični broj" required inputmode="numeric" maxlength="8" />
                <flux:input wire:model="activity_code" label="Šifra delatnosti" />
                <flux:input wire:model="jbkjs" label="JBKJS" description="Samo za korisnike javnih sredstava" maxlength="5" />
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Adresa</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:input wire:model="address" label="Ulica i broj" required />
                </div>
                <flux:input wire:model="city" label="Mesto" required />
                <flux:input wire:model="postal_code" label="Poštanski broj" />
                <flux:input wire:model="country_code" label="Država" description="ISO oznaka, npr. RS" maxlength="2" required />
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>PDV i valuta</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:switch wire:model.live="in_vat_system" label="U sistemu PDV-a"
                        description="Isključi za paušalce i neoporezivi promet" />
                </div>

                @if ($in_vat_system)
                    <flux:input wire:model="vat_registered_at" label="Datum evidentiranja u PDV" type="date" />
                @endif

                <flux:select wire:model="default_currency" label="Podrazumevana valuta" required>
                    @foreach ($currencies as $currency)
                        <flux:select.option value="{{ $currency }}" :selected="$currency === $default_currency">{{ $currency }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Kontakt</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="email" label="E-pošta" type="email" />
                <flux:input wire:model="phone" label="Telefon" />
                <div class="sm:col-span-2">
                    <flux:input wire:model="website" label="Veb adresa" placeholder="https://" />
                </div>
            </div>
        </flux:fieldset>

        @unless ($company)
            <flux:separator />

            <flux:fieldset>
                <flux:legend>Numeracija faktura</flux:legend>

                <flux:callout icon="information-circle" class="mb-4">
                    <flux:callout.text>
                        Unesi poslednji broj fakture iskorišćen u {{ now()->year }}. godini van aplikacije.
                        Prva faktura izdata ovde dobiće broj <strong>{{ $nextNumberPreview }}</strong>.
                    </flux:callout.text>
                </flux:callout>

                <flux:input wire:model.live="last_invoice_number" label="Poslednji iskorišćen broj"
                    type="number" min="0" required />
            </flux:fieldset>
        @endunless

        <flux:separator />

        <flux:switch wire:model="is_active" label="Aktivno" description="Neaktivna pravna lica se ne nude pri fakturisanju" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Sačuvaj</flux:button>
            <flux:button variant="ghost" :href="route('companies.index')" wire:navigate>Odustani</flux:button>
        </div>
    </form>

    @if ($company)
        <div class="mt-10 max-w-3xl">
            <flux:separator class="mb-6" />
            <livewire:companies.bank-accounts :company="$company" />
        </div>
    @endif
</section>
