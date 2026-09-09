<?php

use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\CopyInvoiceToNextPeriod;
use App\Actions\Invoices\IssueInvoice;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Support\InvoiceTotals;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public Invoice $invoice;

    public bool $showCancelModal = false;

    public bool $showSendModal = false;

    public string $cancelReason = '';

    public string $recipient = '';

    public string $messageBody = '';

    public function mount(Invoice $invoice): void
    {
        Gate::authorize('view', $invoice);

        $this->invoice = $invoice;
        $this->recipient = (string) $invoice->partner->email;
    }

    public function issue(IssueInvoice $action): void
    {
        Gate::authorize('issue', $this->invoice);

        try {
            $this->invoice = $action->handle($this->invoice);
        } catch (ValidationException $e) {
            $this->addError('issue', $e->validator->errors()->first());

            return;
        }

        session()->flash('status', "Dokument je izdat pod brojem {$this->invoice->number}.");
    }

    /**
     * For clients that recur without a contract: the same document, one period
     * on, as a fresh draft to check over.
     */
    public function copyToNextPeriod(CopyInvoiceToNextPeriod $action): void
    {
        Gate::authorize('create', Invoice::class);
        Gate::authorize('view', $this->invoice);

        $copy = $action->handle($this->invoice->load('items'));

        session()->flash('status', 'Napravljen je nacrt za '.$copy->periodLabel());

        $this->redirect(route('invoices.edit', $copy), navigate: true);
    }

    public function cancel(CancelInvoice $action): void
    {
        Gate::authorize('cancel', $this->invoice);

        $this->validate([
            'cancelReason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'cancelReason.required' => 'Razlog storniranja je obavezan.',
        ]);

        $this->invoice = $action->handle($this->invoice, auth()->user(), $this->cancelReason);

        $this->showCancelModal = false;
        $this->cancelReason = '';

        session()->flash('status', 'Dokument je storniran.');
    }

    public function send(): void
    {
        Gate::authorize('send', $this->invoice);

        $this->validate([
            'recipient' => ['required', 'email'],
            'messageBody' => ['nullable', 'string', 'max:2000'],
        ], [
            'recipient.required' => 'Unesi adresu primaoca.',
        ]);

        Mail::to($this->recipient)->send(new InvoiceMail($this->invoice, $this->messageBody ?: null));

        $this->invoice->forceFill(['sent_at' => now()])->save();

        $this->showSendModal = false;

        session()->flash('status', "Dokument je poslat na {$this->recipient}.");
    }

    public function deleteDraft(): void
    {
        Gate::authorize('delete', $this->invoice);

        $this->invoice->delete();

        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function with(): array
    {
        $this->invoice->loadMissing(['partner', 'items', 'bankAccount', 'company.primaryBankAccount', 'creator', 'canceller']);

        return [
            'recapitulation' => InvoiceTotals::recapitulation($this->invoice),
            'account' => $this->invoice->bankAccount ?? $this->invoice->company->primaryBankAccount,
        ];
    }
}; ?>

@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
@endphp

<section class="w-full">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('invoices.index')" wire:navigate />
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl">{{ $invoice->type->label() }} {{ $invoice->displayNumber() }}</flux:heading>
                    <flux:badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge>
                </div>
                <flux:subheading>{{ $invoice->partner->name }} · {{ $invoice->periodLabel() }}</flux:subheading>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('update', $invoice)
                <flux:button size="sm" icon="pencil-square" :href="route('invoices.edit', $invoice)" wire:navigate>
                    Izmeni
                </flux:button>
            @endcan

            @can('issue', $invoice)
                <flux:button size="sm" variant="primary" icon="check" wire:click="issue"
                    wire:confirm="Izdati dokument? Posle toga se više ne može menjati.">
                    Izdaj
                </flux:button>
            @endcan

            @if ($invoice->status->isIssued())
                <flux:button size="sm" icon="arrow-down-tray" :href="route('invoices.pdf', $invoice)" target="_blank">
                    PDF
                </flux:button>
            @endif

            @can('send', $invoice)
                <flux:button size="sm" icon="envelope" wire:click="$set('showSendModal', true)">Pošalji</flux:button>
            @endcan

            @can('create', App\Models\Invoice::class)
                <flux:button size="sm" icon="document-duplicate" wire:click="copyToNextPeriod"
                    wire:confirm="Napraviti nacrt za naredni period sa istim stavkama?">
                    Kopiraj u naredni mesec
                </flux:button>
            @endcan

            @can('cancel', $invoice)
                <flux:button size="sm" variant="danger" icon="x-circle" wire:click="$set('showCancelModal', true)">
                    Storniraj
                </flux:button>
            @endcan

            @can('delete', $invoice)
                <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteDraft"
                    wire:confirm="Obrisati nacrt?" />
            @endcan
        </div>
    </div>

    @if (session('status'))
        <flux:callout icon="check-circle" color="lime" class="mt-4">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @error('issue')
        <flux:callout icon="exclamation-triangle" color="red" class="mt-4">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    @if ($invoice->status->value === 'stornirana')
        <flux:callout icon="x-circle" color="red" class="mt-4">
            <flux:callout.heading>Dokument je storniran</flux:callout.heading>
            <flux:callout.text>
                {{ $invoice->cancelled_at?->format('d.m.Y. H:i') }}
                @if ($invoice->canceller) · {{ $invoice->canceller->name }} @endif
                @if ($invoice->cancel_reason) · {{ $invoice->cancel_reason }} @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-4">
        <flux:card>
            <flux:text size="sm" class="text-zinc-500">Datum izdavanja</flux:text>
            <flux:heading size="lg">{{ $invoice->issue_date->format('d.m.Y.') }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm" class="text-zinc-500">Datum prometa</flux:text>
            <flux:heading size="lg">{{ $invoice->supply_date->format('d.m.Y.') }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm" class="text-zinc-500">Rok plaćanja</flux:text>
            <flux:heading size="lg">{{ $invoice->due_date->format('d.m.Y.') }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm" class="text-zinc-500">Za uplatu</flux:text>
            <flux:heading size="lg">{{ $money($invoice->total) }} {{ $invoice->currency }}</flux:heading>
            @if ($invoice->isForeignCurrency())
                <flux:text size="sm" class="text-zinc-500">{{ $money($invoice->total_rsd) }} RSD</flux:text>
            @endif
        </flux:card>
    </div>

    @if ($invoice->fullPaymentReference() || $account)
        <div class="mt-4 flex flex-wrap gap-6 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            @if ($account)
                <div>
                    <div class="text-zinc-500">Račun</div>
                    <div class="font-medium">{{ $account->formattedAccountNumber() }} — {{ $account->bank_name }}</div>
                </div>
            @endif
            @if ($invoice->fullPaymentReference())
                <div>
                    <div class="text-zinc-500">Poziv na broj</div>
                    <div class="font-medium">{{ $invoice->fullPaymentReference() }}</div>
                </div>
            @endif
            @if ($invoice->isForeignCurrency())
                <div>
                    <div class="text-zinc-500">Kurs</div>
                    <div class="font-medium">{{ $money($invoice->exchange_rate) }} RSD</div>
                </div>
            @endif
            @if ($invoice->sent_at)
                <div>
                    <div class="text-zinc-500">Poslato</div>
                    <div class="font-medium">{{ $invoice->sent_at->format('d.m.Y. H:i') }}</div>
                </div>
            @endif
        </div>
    @endif

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>#</flux:table.column>
            <flux:table.column>Naziv</flux:table.column>
            <flux:table.column align="end">Količina</flux:table.column>
            <flux:table.column>JM</flux:table.column>
            <flux:table.column align="end">Cena</flux:table.column>
            <flux:table.column align="end">Rabat</flux:table.column>
            <flux:table.column align="end">PDV</flux:table.column>
            <flux:table.column align="end">Vrednost</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($invoice->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>{{ $loop->iteration }}</flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        {{ $item->name }}
                        @if ($item->description)
                            <div class="text-xs text-zinc-500">{{ $item->description }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }}</flux:table.cell>
                    <flux:table.cell>{{ $item->unit_symbol ?? $item->unit_code }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $money($item->unit_price) }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $item->discount_percent > 0 ? $money($item->discount_percent).'%' : '—' }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $money($item->vat_rate) }}%</flux:table.cell>
                    <flux:table.cell align="end" variant="strong">{{ $money($item->line_subtotal) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-6 grid gap-6 sm:grid-cols-2">
        <div>
            @if ($recapitulation->isNotEmpty())
                <flux:heading size="sm" class="text-zinc-500">Rekapitulacija PDV-a</flux:heading>

                <flux:table class="mt-2">
                    <flux:table.columns>
                        <flux:table.column>Kategorija</flux:table.column>
                        <flux:table.column align="end">Stopa</flux:table.column>
                        <flux:table.column align="end">Osnovica</flux:table.column>
                        <flux:table.column align="end">PDV</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($recapitulation as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ $row['vat_category'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $money($row['vat_rate']) }}%</flux:table.cell>
                                <flux:table.cell align="end">{{ $money($row['base']) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $money($row['vat']) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif

            @if ($invoice->note)
                <div class="mt-4 text-sm">
                    <div class="text-zinc-500">Napomena</div>
                    <p>{{ $invoice->note }}</p>
                </div>
            @endif

            @if ($invoice->internal_note)
                <div class="mt-4 text-sm">
                    <div class="text-zinc-500">Interna napomena (ne štampa se)</div>
                    <p>{{ $invoice->internal_note }}</p>
                </div>
            @endif

            {{-- An issued document can no longer be opened in the form, so the
                 mark has to be visible here. --}}
            @if ($invoice->valid_without_signature)
                <div class="mt-4 text-sm italic text-zinc-500">
                    Ova faktura je validna u elektronskom obliku bez pečata i potpisa!
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">Osnovica</dt>
                    <dd>{{ $money($invoice->subtotal) }} {{ $invoice->currency }}</dd>
                </div>
                @if ($invoice->discount_total > 0)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Rabat</dt>
                        <dd>−{{ $money($invoice->discount_total) }} {{ $invoice->currency }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-zinc-500">PDV</dt>
                    <dd>{{ $money($invoice->vat_total) }} {{ $invoice->currency }}</dd>
                </div>
                <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700">
                    <dt>Za uplatu</dt>
                    <dd>{{ $money($invoice->total) }} {{ $invoice->currency }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <flux:modal wire:model="showCancelModal" class="max-w-md">
        <form wire:submit="cancel" class="space-y-4">
            <div>
                <flux:heading size="lg">Storniranje dokumenta</flux:heading>
                <flux:text class="mt-1">
                    Dokument zadržava svoj broj, a razlog i ko je stornirao ostaju zabeleženi.
                </flux:text>
            </div>

            <flux:input wire:model="cancelReason" label="Razlog storniranja" required />

            <div class="flex gap-2">
                <flux:button type="submit" variant="danger">Storniraj</flux:button>
                <flux:button type="button" variant="ghost" wire:click="$set('showCancelModal', false)">Odustani</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showSendModal" class="max-w-lg">
        <form wire:submit="send" class="space-y-4">
            <div>
                <flux:heading size="lg">Slanje dokumenta</flux:heading>
                <flux:text class="mt-1">PDF se prilaže automatski.</flux:text>
            </div>

            <flux:input wire:model="recipient" label="Primalac" type="email" required />
            <flux:textarea wire:model="messageBody" label="Poruka" rows="4"
                placeholder="Ostavi prazno za podrazumevani tekst" />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Pošalji</flux:button>
                <flux:button type="button" variant="ghost" wire:click="$set('showSendModal', false)">Odustani</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
