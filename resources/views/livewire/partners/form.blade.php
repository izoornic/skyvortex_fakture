<?php

use App\Enums\PartnerType;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Support\CurrentCompany;
use App\Support\PartnerRules;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component {
    public ?Partner $partner = null;

    public string $partner_group_id = '';
    public string $type = '';
    public string $name = '';
    public string $pib = '';
    public string $registration_number = '';
    public string $jmbg = '';
    public string $vat_id = '';
    public string $jbkjs = '';
    public string $address = '';
    public string $city = '';
    public string $postal_code = '';
    public string $country_code = 'RS';
    public bool $in_vat_system = false;
    public string $email = '';
    public string $phone = '';
    public string $contact_person = '';
    public int $payment_days = 15;
    public string $default_currency = 'RSD';
    public string $notes = '';
    public bool $is_active = true;

    public function mount(?Partner $partner = null): void
    {
        $this->partner = $partner?->exists ? $partner : null;

        if ($this->partner) {
            Gate::authorize('update', $this->partner);

            foreach ([
                'name', 'pib', 'registration_number', 'jmbg', 'vat_id', 'jbkjs',
                'address', 'city', 'postal_code', 'country_code', 'email', 'phone',
                'contact_person', 'default_currency', 'notes', 'partner_group_id',
            ] as $field) {
                $this->{$field} = (string) $this->partner->{$field};
            }

            $this->type = $this->partner->type->value;
            $this->in_vat_system = $this->partner->in_vat_system;
            $this->is_active = $this->partner->is_active;
            $this->payment_days = $this->partner->payment_days;

            return;
        }

        Gate::authorize('create', Partner::class);

        $this->type = PartnerType::LegalEntity->value;
        $this->default_currency = $this->company()->default_currency;
    }

    /**
     * Switching the type changes which identifiers apply, so the ones that no
     * longer make sense are cleared instead of being silently carried along.
     */
    public function updatedType(): void
    {
        $type = PartnerType::from($this->type);

        if (! $type->requiresTaxNumber()) {
            $this->pib = '';
            $this->registration_number = '';
        }

        if (! $type->allowsPersonalNumber()) {
            $this->jmbg = '';
        }

        if (! $type->isForeign()) {
            $this->vat_id = '';
            $this->country_code = 'RS';
        }

        $this->resetValidation();
    }

    public function save(): void
    {
        $company = $this->company();
        $type = PartnerType::from($this->type);

        $data = $this->validate(
            PartnerRules::for($company, $type, $this->partner),
            PartnerRules::messages(),
        );

        foreach (['pib', 'registration_number', 'jmbg', 'vat_id', 'jbkjs', 'address',
            'city', 'postal_code', 'email', 'phone', 'contact_person', 'notes',
            'partner_group_id'] as $optional) {
            $data[$optional] = $data[$optional] ?: null;
        }

        if ($this->partner) {
            $this->partner->update($data);
        } else {
            $this->partner = $company->partners()->create($data);
        }

        $this->redirect(route('partners.index'), navigate: true);
    }

    public function with(): array
    {
        $type = PartnerType::from($this->type);

        return [
            'partnerType' => $type,
            'types' => PartnerType::options(),
            'currencies' => Currency::query()->active()->ordered()->get(),
            // An inactive group stays on the list while it still holds this
            // partner, so opening the form does not quietly drop the membership.
            'partnerGroups' => PartnerGroup::query()
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhere('id', $this->partner_group_id ?: 0))
                ->orderBy('name')
                ->get(),
        ];
    }

    private function company(): \App\Models\Company
    {
        return $this->partner?->company ?? app(CurrentCompany::class)->get();
    }
}; ?>

<section class="w-full">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('partners.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $partner ? $partner->name : 'Novi partner' }}</flux:heading>
            <flux:subheading>Tip partnera određuje koji su identifikatori obavezni</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="mt-6 max-w-3xl space-y-8">
        <flux:fieldset>
            <flux:legend>Osnovni podaci</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="type" label="Tip" required>
                    @foreach ($types as $value => $label)
                        <flux:select.option value="{{ $value }}" :selected="$value === $type">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" :label="$partnerType->nameLabel()" required autofocus />

                @if ($partnerType->requiresTaxNumber())
                    <flux:input wire:model="pib" label="PIB" required inputmode="numeric" maxlength="9" />
                    <flux:input wire:model="registration_number" label="Matični broj" required inputmode="numeric" maxlength="8" />
                    <flux:input wire:model="jbkjs" label="JBKJS" description="Samo za korisnike javnih sredstava" maxlength="5" />
                @endif

                @if ($partnerType->allowsPersonalNumber())
                    <div class="sm:col-span-2">
                        <flux:input wire:model="jmbg" label="JMBG" inputmode="numeric" maxlength="13"
                            description="Lični podatak — čuva se šifrovano i ne ulazi u revizioni trag" />
                    </div>
                @endif

                @if ($partnerType->isForeign())
                    <flux:input wire:model="vat_id" label="Poreski broj (VAT ID)" />
                @endif
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Adresa</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:input wire:model="address" label="Ulica i broj" />
                </div>
                <flux:input wire:model="city" label="Mesto" />
                <flux:input wire:model="postal_code" label="Poštanski broj" />
                <flux:input wire:model="country_code" label="Država" description="ISO oznaka, npr. RS" maxlength="2" required />
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Fakturisanje</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="payment_days" label="Rok plaćanja (dana)" type="number" min="0" max="365" required />

                <flux:select wire:model="default_currency" label="Podrazumevana valuta" required>
                    @foreach ($currencies as $currency)
                        <flux:select.option value="{{ $currency->code }}" :selected="$currency->code === $default_currency">
                            {{ $currency->code }} — {{ $currency->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:switch wire:model="in_vat_system" label="U sistemu PDV-a" />
                </div>
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Kontakt</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:select wire:model="partner_group_id" label="Grupa"
                        description="Grupa prima jedan PDF sa fakturama svih svojih partnera. Faktura i dalje glasi na ovog partnera.">
                        <flux:select.option value="" :selected="$partner_group_id === ''">Bez grupe</flux:select.option>
                        @foreach ($partnerGroups as $groupOption)
                            <flux:select.option value="{{ $groupOption->id }}"
                                :selected="(string) $groupOption->id === $partner_group_id">
                                {{ $groupOption->name }}@unless ($groupOption->is_active) (neaktivna)@endunless
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input wire:model="email" label="E-pošta" type="email" />
                <flux:input wire:model="phone" label="Telefon" />
                <div class="sm:col-span-2">
                    <flux:input wire:model="contact_person" label="Kontakt osoba" />
                </div>
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="notes" label="Napomena" rows="3" />
                </div>
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:switch wire:model="is_active" label="Aktivan" description="Neaktivni partneri se ne nude pri fakturisanju" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Sačuvaj</flux:button>
            <flux:button variant="ghost" :href="route('partners.index')" wire:navigate>Odustani</flux:button>
        </div>
    </form>
</section>
