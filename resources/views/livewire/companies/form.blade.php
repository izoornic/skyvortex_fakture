<?php

use App\Actions\Companies\StoreCompanyLogo;
use App\Enums\CompanyType;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceNumberSequence;
use App\Models\VatExemptionReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?Company $company = null;

    public string $type = CompanyType::LegalEntity->value;
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
    public ?int $vat_exemption_reason_id = null;
    public string $default_currency = 'RSD';
    public string $payment_code = Company::DEFAULT_PAYMENT_CODE;
    public string $email = '';
    public string $phone = '';
    public string $website = '';
    public bool $is_active = true;

    /**
     * A freshly picked file, before it is stored. The stored one lives on the
     * company as `logo_path`.
     */
    public $logo = null;

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
                'default_currency', 'payment_code', 'email', 'phone', 'website',
            ] as $field) {
                $this->{$field} = (string) $this->company->{$field};
            }

            $this->type = $this->company->type->value;
            $this->in_vat_system = $this->company->in_vat_system;
            $this->is_active = $this->company->is_active;
            $this->vat_registered_at = $this->company->vat_registered_at?->toDateString() ?? '';
            $this->vat_exemption_reason_id = $this->company->vat_exemption_reason_id;

            return;
        }

        Gate::authorize('create', Company::class);
    }

    /**
     * A stambena zajednica has neither a line of business nor a public funds
     * number, so the fields go away — and what was typed into them goes with
     * them, instead of being saved out of sight.
     */
    public function updatedType(): void
    {
        $type = $this->selectedType();

        if (! $type->hasActivityCode()) {
            $this->activity_code = '';
        }

        if (! $type->canBePublicFunds()) {
            $this->jbkjs = '';
        }
    }

    public function save(StoreCompanyLogo $logos): void
    {
        $data = $this->validate($this->rules(), $this->messages());

        $data['vat_registered_at'] = $data['vat_registered_at'] ?: null;
        $data['vat_exemption_reason_id'] = $data['vat_exemption_reason_id'] ?: null;

        foreach (['short_name', 'postal_code', 'activity_code', 'jbkjs', 'email', 'phone', 'website'] as $optional) {
            $data[$optional] = $data[$optional] ?: null;
        }

        $lastNumber = (int) ($data['last_invoice_number'] ?? 0);
        unset($data['last_invoice_number'], $data['logo']);

        if ($this->company) {
            $this->company->update($data);

            if ($this->logo) {
                $logos->handle($this->company, $this->logo);
            }
        } else {
            // A rejected logo must not leave a half-created company behind, so
            // it is stored inside the same transaction and `$this->company` is
            // only assigned once everything held.
            DB::transaction(function () use ($data, $lastNumber, $logos) {
                $company = Company::create($data);

                InvoiceNumberSequence::create([
                    'company_id' => $company->id,
                    'type' => DocumentType::Invoice,
                    'year' => now()->year,
                    'last_number' => $lastNumber,
                ]);

                if ($this->logo) {
                    $logos->handle($company, $this->logo);
                }

                $this->company = $company;
            });
        }

        $this->reset('logo');

        $this->redirect(route('companies.edit', $this->company), navigate: true);
    }

    public function removeLogo(StoreCompanyLogo $logos): void
    {
        abort_if($this->company === null, 404);

        Gate::authorize('update', $this->company);

        $logos->remove($this->company);

        $this->reset('logo');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [
            'type' => ['required', Rule::enum(CompanyType::class)],
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
            'vat_exemption_reason_id' => ['nullable', Rule::exists('vat_exemption_reasons', 'id')],
            'default_currency' => ['required', 'string', 'size:3'],
            // Tri cifre, prva 1 (gotovinski) ili 2 (bezgotovinski) — tako traži NBS.
            'payment_code' => ['required', 'regex:/^[12][0-9]{2}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_active' => ['boolean'],
            'logo' => [
                'nullable',
                'file',
                'max:'.StoreCompanyLogo::MAX_KILOBYTES,
                'extensions:'.implode(',', StoreCompanyLogo::EXTENSIONS),
            ],
        ];

        if (! $this->company) {
            $rules['last_invoice_number'] = ['required', 'integer', 'min:0', 'max:999999'];
        }

        return $rules;
    }

    /**
     * The property is bound live, so anything can arrive in it. An unknown value
     * must fail validation on save, not take the whole form down while typing.
     */
    private function selectedType(): CompanyType
    {
        return CompanyType::tryFrom($this->type) ?? CompanyType::LegalEntity;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'logo.extensions' => 'Logo mora biti SVG, PNG ili JPG fajl.',
            'logo.max' => 'Logo je veći od 2 MB.',
        ];
    }

    /**
     * A refused file stays in the property so the user sees which one failed,
     * and Livewire cannot make a preview of just any file — asking it for one
     * throws and takes the whole form down with it.
     */
    private function logoPreview(): ?string
    {
        return $this->logo instanceof TemporaryUploadedFile && $this->logo->isPreviewable()
            ? $this->logo->temporaryUrl()
            : null;
    }

    public function with(): array
    {
        return [
            'logoPreview' => $this->logoPreview(),
            'types' => CompanyType::options(),
            'companyType' => $this->selectedType(),
            'currencies' => ['RSD', 'EUR', 'USD', 'CHF', 'GBP'],
            'exemptionReasons' => VatExemptionReason::query()->active()->ordered()->get(),
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
                    <flux:select wire:model.live="type" label="Tip" required>
                        @foreach ($types as $value => $label)
                            <flux:select.option value="{{ $value }}" :selected="$value === $type">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="sm:col-span-2">
                    <flux:input wire:model="name" label="Pun naziv" required autofocus />
                </div>
                <flux:input wire:model="short_name" label="Skraćeni naziv" description="Prikazuje se u prekidaču i listama" />
                <flux:input wire:model="pib" label="PIB" required inputmode="numeric" maxlength="9" />
                <flux:input wire:model="registration_number" label="Matični broj" required inputmode="numeric" maxlength="8" />

                @if ($companyType->hasActivityCode())
                    <flux:input wire:model="activity_code" label="Šifra delatnosti" />
                @endif

                @if ($companyType->canBePublicFunds())
                    <flux:input wire:model="jbkjs" label="JBKJS" description="Samo za korisnike javnih sredstava" maxlength="5" />
                @endif
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

                <flux:input wire:model="payment_code" label="Šifra plaćanja" maxlength="3" inputmode="numeric"
                    description="Upisuje se u NBS IPS QR kôd. 221 — bezgotovinski promet robe i usluga." required />

                <div class="sm:col-span-2">
                    @if ($exemptionReasons->isNotEmpty())
                        <flux:select wire:model="vat_exemption_reason_id" label="Podrazumevani osnov oslobođenja od PDV-a"
                            description="Upisuje se na svaki dokument na kojem PDV nije obračunat. Bez njega faktura ne prolazi na SEF-u.">
                            <flux:select.option value="" :selected="! $vat_exemption_reason_id">Nije izabran</flux:select.option>
                            @foreach ($exemptionReasons as $reason)
                                <flux:select.option value="{{ $reason->id }}" :selected="$reason->id === $vat_exemption_reason_id">
                                    {{ $reason->code }} — {{ $reason->description }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:callout icon="exclamation-triangle" color="amber">
                            <flux:callout.text>
                                Šifarnik osnova oslobođenja je prazan, pa se pravni osnov ne može izabrati.
                                Popuni ga u Šifarnicima.
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                </div>

                @unless ($in_vat_system || $vat_exemption_reason_id)
                    <div class="sm:col-span-2">
                        <flux:callout icon="exclamation-triangle" color="amber">
                            <flux:callout.text>
                                Pravno lice nije u sistemu PDV-a, a osnov oslobođenja nije izabran —
                                na fakturama neće pisati po kom članu PDV nije obračunat.
                            </flux:callout.text>
                        </flux:callout>
                    </div>
                @endunless
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

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Logo</flux:legend>

            <div class="flex flex-wrap items-start gap-6">
                {{-- overflow-hidden: an SVG without width/height attributes takes whatever room it is given. --}}
                <div class="flex h-24 w-40 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-zinc-300 bg-white p-2 dark:border-zinc-600 dark:bg-zinc-900">
                    @if ($logoPreview)
                        <img src="{{ $logoPreview }}" alt="Izabrani logo" class="max-h-full max-w-full object-contain">
                    @elseif ($company?->hasLogo())
                        <img src="{{ $company->logoUrl() }}" alt="Logo pravnog lica" class="max-h-full max-w-full object-contain">
                    @else
                        <flux:icon.photo class="size-8 text-zinc-300 dark:text-zinc-600" />
                    @endif
                </div>

                <div class="min-w-64 flex-1 space-y-3">
                    <flux:input type="file" wire:model="logo" label="Logo izdavaoca"
                        description="SVG, PNG ili JPG do 2 MB. SVG ostaje oštar na štampi."
                        accept=".svg,.png,.jpg,.jpeg" />

                    @if ($company?->hasLogo())
                        <flux:button size="sm" variant="ghost" icon="trash" type="button"
                            wire:click="removeLogo" wire:confirm="Ukloniti logo?">
                            Ukloni logo
                        </flux:button>
                    @endif
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
