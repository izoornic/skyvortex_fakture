<?php

use App\Enums\BillingMode;
use App\Enums\ContractFrequency;
use App\Enums\VatCategory;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use App\Support\CurrentCompany;
use App\Support\InvoiceTotals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?Contract $contract = null;

    public ?int $partner_id = null;
    public string $name = '';
    public string $reference = '';
    public string $frequency = ContractFrequency::Monthly->value;
    public string $billing_mode = BillingMode::Arrears->value;
    public int $generation_day = 1;
    public string $starts_on = '';
    public string $ends_on = '';
    public string $currency = 'RSD';
    public int $payment_days = 15;
    public ?int $bank_account_id = null;
    public string $note = '';
    public bool $valid_without_signature = true;
    public string $internal_note = '';
    public bool $is_active = true;

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?Contract $contract = null): void
    {
        $this->contract = $contract?->exists ? $contract : null;

        if ($this->contract) {
            Gate::authorize('update', $this->contract);
            $this->fillFromContract();

            return;
        }

        Gate::authorize('create', Contract::class);
        $this->fillDefaults();
    }

    private function fillFromContract(): void
    {
        $contract = $this->contract;

        $this->partner_id = $contract->partner_id;
        $this->name = $contract->name;
        $this->reference = (string) $contract->reference;
        $this->frequency = $contract->frequency->value;
        $this->billing_mode = $contract->billing_mode->value;
        $this->generation_day = $contract->generation_day;
        $this->starts_on = $contract->starts_on->toDateString();
        $this->ends_on = $contract->ends_on?->toDateString() ?? '';
        $this->currency = $contract->currency;
        $this->payment_days = $contract->payment_days;
        $this->bank_account_id = $contract->bank_account_id;
        $this->note = (string) $contract->note;
        $this->valid_without_signature = (bool) $contract->valid_without_signature;
        $this->internal_note = (string) $contract->internal_note;
        $this->is_active = $contract->is_active;

        $this->items = $contract->items->map(fn ($item) => [
            'name' => $item->name,
            'description' => (string) $item->description,
            'unit_code' => $item->unit_code,
            'quantity' => (string) (float) $item->quantity,
            'unit_price' => (string) (float) $item->unit_price,
            'discount_percent' => (string) (float) $item->discount_percent,
            'vat_rate' => (string) (float) $item->vat_rate,
            'vat_category' => $item->vat_category->value,
            'vat_exemption_reason_id' => $item->vat_exemption_reason_id,
        ])->all();

        if ($this->items === []) {
            $this->addItem();
        }
    }

    private function fillDefaults(): void
    {
        $company = $this->company();

        $this->starts_on = now()->startOfMonth()->toDateString();
        $this->currency = $company->default_currency;
        $this->bank_account_id = $company->primaryBankAccount?->id ?? $company->bankAccounts()->first()?->id;

        $this->addItem();
    }

    /**
     * Picking a partner carries over its payment terms and currency, the same
     * way the invoice form does.
     */
    public function updatedPartnerId(): void
    {
        $partner = Partner::find($this->partner_id);

        if (! $partner) {
            return;
        }

        $this->payment_days = $partner->payment_days;
        $this->currency = $partner->default_currency;
    }

    public function addItem(): void
    {
        $company = $this->company();

        $this->items[] = [
            'name' => '',
            'description' => '',
            'unit_code' => 'MON',
            'quantity' => '1',
            'unit_price' => '0',
            'discount_percent' => '0',
            'vat_rate' => $company->in_vat_system ? (string) ($this->defaultVatRate()?->rate ?? 20) : '0',
            'vat_category' => $company->in_vat_system ? VatCategory::Standard->value : VatCategory::OutOfScope->value,
            'vat_exemption_reason_id' => $company->in_vat_system ? null : $company->vat_exemption_reason_id,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $company = $this->company();

        $data = $this->validate($this->rules());

        $attributes = [
            'partner_id' => $data['partner_id'],
            'name' => $data['name'],
            'reference' => $data['reference'] ?: null,
            'frequency' => $data['frequency'],
            'billing_mode' => $data['billing_mode'],
            'generation_day' => $data['generation_day'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?: null,
            'currency' => $data['currency'],
            'payment_days' => $data['payment_days'],
            'bank_account_id' => $data['bank_account_id'] ?: null,
            'note' => $data['note'] ?: null,
            'valid_without_signature' => $data['valid_without_signature'],
            'internal_note' => $data['internal_note'] ?: null,
            'is_active' => $data['is_active'],
        ];

        DB::transaction(function () use ($company, $attributes, $data) {
            if ($this->contract) {
                $this->contract->update($attributes);
            } else {
                $this->contract = $company->contracts()->create([
                    ...$attributes,
                    'created_by' => auth()->id(),
                ]);
            }

            $this->contract->items()->delete();

            foreach (array_values($data['items']) as $position => $item) {
                $this->contract->items()->create([
                    'sort_order' => $position,
                    'name' => $item['name'],
                    'description' => $item['description'] ?: null,
                    'unit_code' => $item['unit_code'],
                    'unit_symbol' => UnitOfMeasure::firstWhere('code', $item['unit_code'])?->symbol,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?: 0,
                    'vat_rate' => $item['vat_rate'] ?: 0,
                    'vat_category' => $item['vat_category'],
                    'vat_exemption_reason_id' => $item['vat_exemption_reason_id'] ?: null,
                ]);
            }
        });

        $this->redirect(route('contracts.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $company = $this->company();

        return [
            'partner_id' => [
                'required',
                Rule::exists('partners', 'id')->where('company_id', $company->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', Rule::enum(ContractFrequency::class)],
            'billing_mode' => ['required', Rule::enum(BillingMode::class)],
            'generation_day' => ['required', 'integer', 'min:1', 'max:31'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'payment_days' => ['required', 'integer', 'min:0', 'max:365'],
            'bank_account_id' => [
                'nullable',
                Rule::exists('bank_accounts', 'id')->where('company_id', $company->id),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            'valid_without_signature' => ['boolean'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.unit_code' => ['required', 'string', Rule::exists('units_of_measure', 'code')],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_category' => ['required', Rule::enum(VatCategory::class)],
            'items.*.vat_exemption_reason_id' => ['nullable', Rule::exists('vat_exemption_reasons', 'id')],
        ];
    }

    private function company(): \App\Models\Company
    {
        $company = app(CurrentCompany::class)->get();

        abort_if($company === null, 409, 'Nije izabrano pravno lice.');

        return $company;
    }

    private function defaultVatRate(): ?VatRate
    {
        return VatRate::query()->where('is_default', true)->first()
            ?? VatRate::query()->orderByDesc('rate')->first();
    }

    /**
     * What the contract will do next, spelled out — the generation day and the
     * billing direction together are easy to get wrong in the head.
     *
     * @return array{on: Carbon, period: Carbon, supply: Carbon}
     */
    private function nextRunPreview(): array
    {
        $mode = BillingMode::from($this->billing_mode);
        $day = max(1, min(31, $this->generation_day));

        $month = now()->startOfMonth();
        $on = $month->copy()->setDay(min($day, $month->daysInMonth));

        if ($on->lt(now()->startOfDay())) {
            $month->addMonth();
            $on = $month->copy()->setDay(min($day, $month->daysInMonth));
        }

        $period = $mode->periodFor($on);

        return ['on' => $on, 'period' => $period, 'supply' => $mode->supplyDateFor($period)];
    }

    public function with(): array
    {
        $company = $this->company();
        $items = array_map(fn (array $item) => $item, $this->items);

        return [
            'company' => $company,
            'partners' => Partner::query()->active()->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->orderBy('sort_order')->get(),
            'vatRates' => VatRate::query()->orderByDesc('rate')->get(),
            'vatCategories' => VatCategory::options(),
            'exemptionReasons' => VatExemptionReason::query()->active()->ordered()->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'billingModes' => BillingMode::cases(),
            'frequencies' => ContractFrequency::cases(),
            'totals' => InvoiceTotals::forDocument($items),
            'nextRun' => $this->nextRunPreview(),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('contracts.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $contract ? $contract->name : 'Novi ugovor' }}</flux:heading>
            <flux:subheading>Šablon po kome se svakog meseca pravi nacrt fakture</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="mt-6 max-w-3xl space-y-8">
        <flux:fieldset>
            <flux:legend>Ugovor</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:select wire:model.live="partner_id" label="Partner" required>
                        <flux:select.option value="" :selected="! $partner_id">— izaberi partnera —</flux:select.option>
                        @foreach ($partners as $partnerOption)
                            <flux:select.option value="{{ $partnerOption->id }}" :selected="$partnerOption->id === $partner_id">
                                {{ $partnerOption->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input wire:model="name" label="Naziv ugovora" description="Vidi se samo u aplikaciji" required />
                <flux:input wire:model="reference" label="Broj ugovora" description="Ako postoji potpisan ugovor" />
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Ponavljanje</flux:legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="frequency" label="Učestalost" required>
                    @foreach ($frequencies as $frequencyOption)
                        <flux:select.option value="{{ $frequencyOption->value }}" :selected="$frequencyOption->value === $frequency">
                            {{ $frequencyOption->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live="generation_day" label="Dan u mesecu" type="number" min="1" max="31"
                    description="Kraći mesec skraćuje i ovaj dan" required />

                <div class="sm:col-span-2">
                    <flux:select wire:model.live="billing_mode" label="Obračun" required>
                        @foreach ($billingModes as $modeOption)
                            <flux:select.option value="{{ $modeOption->value }}" :selected="$modeOption->value === $billing_mode">
                                {{ $modeOption->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text size="sm" class="mt-1">{{ \App\Enums\BillingMode::from($billing_mode)->description() }}</flux:text>
                </div>

                <div class="sm:col-span-2">
                    <flux:callout icon="calendar-days">
                        <flux:callout.text>
                            Sledeći nacrt: <strong>{{ $nextRun['on']->format('d.m.Y.') }}</strong>,
                            za period <strong>{{ \App\Support\PeriodLabel::forDate($nextRun['period']) }}</strong>,
                            sa datumom prometa <strong>{{ $nextRun['supply']->format('d.m.Y.') }}</strong>.
                        </flux:callout.text>
                    </flux:callout>
                </div>

                <flux:input wire:model="starts_on" label="Važi od" type="date" required />
                <flux:input wire:model="ends_on" label="Važi do" type="date" description="Prazno — dok se ne prekine" />
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Valuta i plaćanje</flux:legend>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model.live="currency" label="Valuta" required>
                    @foreach ($currencies as $currencyOption)
                        <flux:select.option value="{{ $currencyOption->code }}" :selected="$currencyOption->code === $this->currency">
                            {{ $currencyOption->code }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="payment_days" label="Rok plaćanja (dana)" type="number" min="0" required />

                <flux:select wire:model="bank_account_id" label="Račun za uplatu">
                    <flux:select.option value="" :selected="! $bank_account_id">Primarni račun</flux:select.option>
                    @foreach ($company->bankAccounts as $account)
                        <flux:select.option value="{{ $account->id }}" :selected="$account->id === $bank_account_id">
                            {{ $account->formattedAccountNumber() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($currency !== 'RSD')
                <flux:callout icon="information-circle" class="mt-4">
                    <flux:callout.text>
                        Kurs se ne čuva na ugovoru. Nacrt dobija poslednji kurs korišćen za {{ $currency }},
                        a pre izdavanja se proverava i, ako treba, ispravlja na fakturi.
                    </flux:callout.text>
                </flux:callout>
            @endif
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Stavke</flux:legend>

            @error('items')
                <flux:callout icon="exclamation-triangle" color="red" class="mb-4">
                    <flux:callout.text>{{ $message }}</flux:callout.text>
                </flux:callout>
            @enderror

            <div class="space-y-4">
                @foreach ($items as $index => $item)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="item-{{ $index }}">
                        <div class="flex items-start justify-between gap-4">
                            <flux:heading size="sm" class="text-zinc-500">Stavka {{ $index + 1 }}</flux:heading>

                            @if (count($items) > 1)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeItem({{ $index }})" />
                            @endif
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-6">
                            <div class="sm:col-span-4">
                                <flux:input wire:model="items.{{ $index }}.name" label="Naziv" required />
                            </div>

                            <flux:select wire:model="items.{{ $index }}.unit_code" label="Jedinica">
                                @foreach ($units as $unit)
                                    <flux:select.option value="{{ $unit->code }}" :selected="$unit->code === $item['unit_code']">{{ $unit->displayName() }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model.live.debounce.400ms="items.{{ $index }}.quantity"
                                label="Količina" type="number" step="0.001" min="0" required />

                            <div class="sm:col-span-6">
                                <flux:input wire:model="items.{{ $index }}.description" label="Opis" />
                            </div>

                            <div class="sm:col-span-2">
                                <flux:input wire:model.live.debounce.400ms="items.{{ $index }}.unit_price"
                                    label="Jedinična cena" type="number" step="0.0001" min="0" required />
                            </div>

                            <flux:input wire:model.live.debounce.400ms="items.{{ $index }}.discount_percent"
                                label="Rabat %" type="number" step="0.01" min="0" max="100" />

                            @if ($company->in_vat_system)
                                <flux:select wire:model.live="items.{{ $index }}.vat_rate" label="PDV stopa">
                                    @foreach ($vatRates as $rate)
                                        <flux:select.option value="{{ (float) $rate->rate }}" :selected="(float) $rate->rate === (float) $item['vat_rate']">
                                            {{ $rate->rate }}%
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>

                                <div class="sm:col-span-2">
                                    <flux:select wire:model="items.{{ $index }}.vat_category" label="PDV kategorija">
                                        @foreach ($vatCategories as $value => $label)
                                            <flux:select.option value="{{ $value }}" :selected="$value === $item['vat_category']">{{ $label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            @endif

                            @if ($exemptionReasons->isNotEmpty())
                                <div class="sm:col-span-6">
                                    <flux:select wire:model="items.{{ $index }}.vat_exemption_reason_id"
                                        label="Osnov oslobođenja od PDV-a"
                                        description="Prenosi se na svaku fakturu iz ovog ugovora. Prazno — uzima se osnov sa pravnog lica.">
                                        <flux:select.option value="" :selected="! ($item['vat_exemption_reason_id'] ?? null)">
                                            Sa pravnog lica
                                        </flux:select.option>
                                        @foreach ($exemptionReasons as $reason)
                                            <flux:select.option value="{{ $reason->id }}"
                                                :selected="$reason->id === ($item['vat_exemption_reason_id'] ?? null)">
                                                {{ $reason->code }} — {{ $reason->description }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 text-right text-sm text-zinc-500">
                            Vrednost stavke:
                            <strong class="text-zinc-900 dark:text-white">
                                {{ number_format(\App\Support\InvoiceTotals::forLine($item)['line_subtotal'], 2, ',', '.') }}
                                {{ $currency }}
                            </strong>
                        </div>
                    </div>
                @endforeach
            </div>

            <flux:button class="mt-4" size="sm" icon="plus" wire:click="addItem" type="button">Dodaj stavku</flux:button>

            <div class="mt-4 flex justify-end gap-6 text-sm">
                <span class="text-zinc-500">Mesečno, bez PDV-a:
                    <strong class="text-zinc-900 dark:text-white">{{ number_format($totals['subtotal'], 2, ',', '.') }} {{ $currency }}</strong>
                </span>
                <span class="text-zinc-500">Sa PDV-om:
                    <strong class="text-zinc-900 dark:text-white">{{ number_format($totals['total'], 2, ',', '.') }} {{ $currency }}</strong>
                </span>
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Napomene</flux:legend>

            <div class="grid gap-4">
                <flux:textarea wire:model="note" label="Napomena na dokumentu"
                    description="Prepisuje se na svaku fakturu iz ovog ugovora" rows="2" />

                <flux:checkbox wire:model="valid_without_signature"
                    label="Validno bez pečata i potpisa"
                    description="Svaki nacrt iz ovog ugovora dobija oznaku, pa se na fakturi štampa: „Ova faktura je validna u elektronskom obliku bez pečata i potpisa!”" />

                <flux:textarea wire:model="internal_note" label="Interna napomena" rows="2" />
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:switch wire:model="is_active" label="Aktivan"
            description="Neaktivan ugovor ne pravi nacrte, a ostaje uz svoje fakture" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Sačuvaj</flux:button>
            <flux:button variant="ghost" :href="route('contracts.index')" wire:navigate>Odustani</flux:button>
        </div>
    </form>
</section>
