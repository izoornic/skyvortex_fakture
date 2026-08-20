<?php

use App\Actions\Invoices\SaveInvoiceItems;
use App\Enums\DocumentType;
use App\Enums\VatCategory;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use App\Support\CurrentCompany;
use App\Support\InvoiceTotals;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?Invoice $invoice = null;

    public string $type = '';
    public ?int $partner_id = null;
    public int $period_year = 0;
    public int $period_month = 0;
    public string $issue_date = '';
    public string $supply_date = '';
    public string $due_date = '';
    public string $currency = 'RSD';
    public string $exchange_rate = '1';
    public ?int $bank_account_id = null;
    public ?int $vat_exemption_reason_id = null;
    public string $place_of_issue = '';
    public string $note = '';
    public string $internal_note = '';

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?Invoice $invoice = null): void
    {
        $this->invoice = $invoice?->exists ? $invoice : null;

        if ($this->invoice) {
            Gate::authorize('update', $this->invoice);
            $this->fillFromInvoice();

            return;
        }

        Gate::authorize('create', Invoice::class);
        $this->fillDefaults();
    }

    private function fillFromInvoice(): void
    {
        $invoice = $this->invoice;

        $this->type = $invoice->type->value;
        $this->partner_id = $invoice->partner_id;
        $this->period_year = $invoice->period_year;
        $this->period_month = $invoice->period_month;
        $this->issue_date = $invoice->issue_date->toDateString();
        $this->supply_date = $invoice->supply_date->toDateString();
        $this->due_date = $invoice->due_date->toDateString();
        $this->currency = $invoice->currency;
        $this->exchange_rate = (string) $invoice->exchange_rate;
        $this->bank_account_id = $invoice->bank_account_id;
        $this->vat_exemption_reason_id = $invoice->vat_exemption_reason_id;
        $this->place_of_issue = (string) $invoice->place_of_issue;
        $this->note = (string) $invoice->note;
        $this->internal_note = (string) $invoice->internal_note;

        $this->items = $invoice->items->map(fn ($item) => [
            'name' => $item->name,
            'description' => (string) $item->description,
            'unit_code' => $item->unit_code,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'discount_percent' => (string) $item->discount_percent,
            'vat_rate' => (string) $item->vat_rate,
            'vat_category' => $item->vat_category->value,
        ])->all();
    }

    private function fillDefaults(): void
    {
        $company = $this->company();

        $this->type = DocumentType::Invoice->value;
        $this->period_year = now()->year;
        $this->period_month = now()->month;
        $this->issue_date = now()->toDateString();
        $this->supply_date = now()->toDateString();
        $this->due_date = now()->addDays(15)->toDateString();
        $this->currency = $company->default_currency;
        $this->place_of_issue = (string) $company->city;
        $this->bank_account_id = $company->primaryBankAccount?->id ?? $company->bankAccounts()->first()?->id;

        $this->addItem();
    }

    /**
     * Picking a partner carries over its payment terms and currency.
     */
    public function updatedPartnerId(): void
    {
        $partner = Partner::find($this->partner_id);

        if (! $partner) {
            return;
        }

        $this->due_date = now()->parse($this->issue_date)->addDays($partner->payment_days)->toDateString();
        $this->currency = $partner->default_currency;
    }

    public function addItem(): void
    {
        $company = $this->company();

        $this->items[] = [
            'name' => '',
            'description' => '',
            'unit_code' => 'H87',
            'quantity' => '1',
            'unit_price' => '0',
            'discount_percent' => '0',
            'vat_rate' => $company->in_vat_system ? (string) ($this->defaultVatRate()?->rate ?? 20) : '0',
            'vat_category' => $company->in_vat_system ? VatCategory::Standard->value : VatCategory::OutOfScope->value,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);
    }

    public function save(bool $andIssue = false): void
    {
        $company = $this->company();

        $data = $this->validate($this->rules(), $this->messages());

        $attributes = [
            'type' => $data['type'],
            'partner_id' => $data['partner_id'],
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
            'issue_date' => $data['issue_date'],
            'supply_date' => $data['supply_date'],
            'due_date' => $data['due_date'],
            'currency' => $data['currency'],
            'exchange_rate' => $data['currency'] === 'RSD' ? 1 : (float) $data['exchange_rate'],
            'bank_account_id' => $data['bank_account_id'] ?: null,
            'vat_exemption_reason_id' => $data['vat_exemption_reason_id'] ?: null,
            'place_of_issue' => $data['place_of_issue'] ?: null,
            'note' => $data['note'] ?: null,
            'internal_note' => $data['internal_note'] ?: null,
        ];

        if ($this->invoice) {
            $this->invoice->update($attributes);
        } else {
            $this->invoice = $company->invoices()->create([
                ...$attributes,
                'created_by' => auth()->id(),
            ]);
        }

        $items = array_map(function (array $item) {
            $item['unit_symbol'] = UnitOfMeasure::firstWhere('code', $item['unit_code'])?->symbol;

            return $item;
        }, $data['items']);

        app(SaveInvoiceItems::class)->handle($this->invoice, $items);

        $this->redirect(route('invoices.show', $this->invoice), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $company = $this->company();
        $foreignCurrency = $this->currency !== 'RSD';

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'partner_id' => [
                'required',
                Rule::exists('partners', 'id')->where('company_id', $company->id),
            ],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'issue_date' => ['required', 'date'],
            'supply_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'exchange_rate' => [$foreignCurrency ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'bank_account_id' => [
                'nullable',
                Rule::exists('bank_accounts', 'id')->where('company_id', $company->id),
            ],
            'vat_exemption_reason_id' => ['nullable', Rule::exists('vat_exemption_reasons', 'id')],
            'place_of_issue' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.unit_code' => ['required', 'string', Rule::exists('units_of_measure', 'code')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_category' => ['required', Rule::enum(VatCategory::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'items.required' => 'Dokument mora imati bar jednu stavku.',
            'items.min' => 'Dokument mora imati bar jednu stavku.',
            'items.*.name.required' => 'Naziv stavke je obavezan.',
            'items.*.quantity.gt' => 'Količina mora biti veća od nule.',
            'exchange_rate.required' => 'Za stranu valutu mora biti unet kurs.',
            'due_date.after_or_equal' => 'Rok plaćanja ne može biti pre datuma izdavanja.',
        ];
    }

    public function with(): array
    {
        $company = $this->company();
        $totals = InvoiceTotals::forDocument($this->items);

        return [
            'company' => $company,
            'partners' => Partner::query()->active()->orderBy('name')->get(['id', 'name', 'pib']),
            'currencies' => Currency::query()->active()->ordered()->get(),
            'units' => UnitOfMeasure::query()->active()->ordered()->get(),
            'vatRates' => VatRate::query()->validOn(now()->parse($this->supply_date ?: 'now'))->ordered()->get(),
            'vatCategories' => VatCategory::options(),
            'exemptionReasons' => VatExemptionReason::query()->active()->ordered()->get(),
            'documentTypes' => DocumentType::options(),
            'totals' => $totals,
            'totalsRsd' => InvoiceTotals::inDinars(
                $totals['subtotal'],
                $totals['vat_total'],
                $totals['total'],
                (float) ($this->exchange_rate ?: 1),
            ),
        ];
    }

    private function defaultVatRate(): ?VatRate
    {
        return VatRate::query()->where('is_default', true)->first();
    }

    private function company(): \App\Models\Company
    {
        return $this->invoice?->company ?? app(CurrentCompany::class)->get();
    }
}; ?>

<section class="w-full">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('invoices.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $invoice ? 'Izmena nacrta' : 'Novi dokument' }}</flux:heading>
            <flux:subheading>{{ $company->displayName() }}</flux:subheading>
        </div>
    </div>

    @unless ($company->in_vat_system)
        <flux:callout icon="information-circle" class="mt-6">
            <flux:callout.text>
                Izdavalac nije u sistemu PDV-a, pa se PDV ne obračunava. Osnov oslobođenja
                se bira na nivou dokumenta.
            </flux:callout.text>
        </flux:callout>
    @endunless

    <form wire:submit="save" class="mt-6 space-y-8">
        <flux:fieldset>
            <flux:legend>Zaglavlje</flux:legend>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="type" label="Vrsta dokumenta" required>
                    @foreach ($documentTypes as $value => $label)
                        <flux:select.option value="{{ $value }}" :selected="$value === $type">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:select wire:model.live="partner_id" label="Partner" required>
                        <flux:select.option value="" class="placeholder" :selected="! $partner_id">Izaberi partnera</flux:select.option>
                        @foreach ($partners as $partner)
                            <flux:select.option value="{{ $partner->id }}" :selected="$partner->id === $partner_id">
                                {{ $partner->name }}{{ $partner->pib ? ' — '.$partner->pib : '' }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input wire:model="period_year" label="Period — godina" type="number" min="2000" max="2100" required />
                <flux:input wire:model="period_month" label="Period — mesec" type="number" min="1" max="12" required />
                <flux:input wire:model="place_of_issue" label="Mesto izdavanja" />

                <flux:input wire:model="issue_date" label="Datum izdavanja" type="date" required />
                <flux:input wire:model.live="supply_date" label="Datum prometa" type="date"
                    description="Određuje poreski period" required />
                <flux:input wire:model="due_date" label="Rok plaćanja" type="date" required />
            </div>
        </flux:fieldset>

        <flux:separator />

        <flux:fieldset>
            <flux:legend>Valuta i račun</flux:legend>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model.live="currency" label="Valuta" required>
                    @foreach ($currencies as $currency)
                        <flux:select.option value="{{ $currency->code }}" :selected="$currency->code === $this->currency">{{ $currency->code }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($currency !== 'RSD')
                    <flux:input wire:model.live="exchange_rate" label="Kurs" type="number" step="0.000001" min="0"
                        description="Unosi se ručno" required />
                @endif

                <flux:select wire:model="bank_account_id" label="Račun za uplatu">
                    <flux:select.option value="" :selected="! $bank_account_id">Primarni račun</flux:select.option>
                    @foreach ($company->bankAccounts as $account)
                        <flux:select.option value="{{ $account->id }}" :selected="$account->id === $bank_account_id">
                            {{ $account->formattedAccountNumber() }} — {{ $account->bank_name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($exemptionReasons->isNotEmpty())
                <div class="mt-4 max-w-xl">
                    <flux:select wire:model="vat_exemption_reason_id" label="Osnov oslobođenja od PDV-a">
                        <flux:select.option value="" :selected="! $vat_exemption_reason_id">Nije primenljivo</flux:select.option>
                        @foreach ($exemptionReasons as $reason)
                            <flux:select.option value="{{ $reason->id }}" :selected="$reason->id === $vat_exemption_reason_id">
                                {{ $reason->code }} — {{ $reason->description }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @elseif (! $company->in_vat_system)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-4">
                    <flux:callout.text>
                        Šifarnik osnova oslobođenja je prazan, pa se na dokument neće ispisati
                        pravni osnov. Popuni ga u Šifarnicima.
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

            <flux:button class="mt-4" size="sm" icon="plus" wire:click="addItem" type="button">
                Dodaj stavku
            </flux:button>
        </flux:fieldset>

        <flux:separator />

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="space-y-4">
                <flux:textarea wire:model="note" label="Napomena na dokumentu" rows="3" />
                <flux:textarea wire:model="internal_note" label="Interna napomena"
                    description="Ne štampa se na dokumentu" rows="2" />
            </div>

            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm" class="text-zinc-500">Rekapitulacija</flux:heading>

                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Osnovica</dt>
                        <dd>{{ number_format($totals['subtotal'], 2, ',', '.') }} {{ $currency }}</dd>
                    </div>
                    @if ($totals['discount_total'] > 0)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Rabat</dt>
                            <dd>−{{ number_format($totals['discount_total'], 2, ',', '.') }} {{ $currency }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">PDV</dt>
                        <dd>{{ number_format($totals['vat_total'], 2, ',', '.') }} {{ $currency }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700">
                        <dt>Za uplatu</dt>
                        <dd>{{ number_format($totals['total'], 2, ',', '.') }} {{ $currency }}</dd>
                    </div>
                    @if ($currency !== 'RSD')
                        <div class="flex justify-between text-zinc-500">
                            <dt>Protivvrednost</dt>
                            <dd>{{ number_format($totalsRsd['total_rsd'], 2, ',', '.') }} RSD</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Sačuvaj nacrt</flux:button>
            <flux:button variant="ghost" :href="route('invoices.index')" wire:navigate>Odustani</flux:button>
        </div>
    </form>
</section>
