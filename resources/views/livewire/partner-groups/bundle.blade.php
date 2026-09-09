<?php

use App\Actions\PartnerGroups\CollectGroupInvoices;
use App\Mail\PartnerGroupBundleMail;
use App\Models\PartnerGroup;
use App\Support\CurrentCompany;
use App\Support\PeriodLabel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * A group of fifty communities makes an attachment a mail server may
     * refuse. Above this many invoices the screen says so before sending.
     */
    private const LARGE_BUNDLE = 40;

    #[Url(as: 'grupa', except: '')]
    public string $groupId = '';

    #[Url(as: 'god')]
    public int $year = 0;

    #[Url(as: 'mes')]
    public ?int $month = null;

    public string $recipient = '';

    public string $messageBody = '';

    public bool $showSendModal = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', PartnerGroup::class);

        $this->year = $this->year ?: now()->year;
        $this->month ??= now()->month;
    }

    public function previousMonth(): void
    {
        $date = now()->setDate($this->year, $this->month ?: 1, 1)->subMonth();

        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = now()->setDate($this->year, $this->month ?: 1, 1)->addMonth();

        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function showWholeYear(): void
    {
        $this->month = null;
    }

    public function openSendModal(): void
    {
        $group = $this->group();

        if (! $group) {
            return;
        }

        $this->recipient = (string) $group->email;
        $this->showSendModal = true;
    }

    public function send(): void
    {
        $group = $this->group();

        if (! $group) {
            return;
        }

        Gate::authorize('view', $group);

        $this->validate([
            'recipient' => ['required', 'email'],
            'messageBody' => ['nullable', 'string', 'max:2000'],
        ], [
            'recipient.required' => 'Unesi adresu primaoca.',
        ]);

        if (app(CollectGroupInvoices::class)->handle($group, $this->year, $this->month)->isEmpty()) {
            $this->addError('bundle', 'Za izabrani period nema izdatih faktura ove grupe.');

            return;
        }

        Mail::to($this->recipient)->send(
            new PartnerGroupBundleMail($group, $this->year, $this->month, $this->messageBody ?: null)
        );

        $this->showSendModal = false;

        session()->flash('status', "Pošiljka je poslata na {$this->recipient}.");
    }

    public function group(): ?PartnerGroup
    {
        return $this->groupId === ''
            ? null
            : PartnerGroup::query()->find((int) $this->groupId);
    }

    public function with(): array
    {
        $group = $this->group();
        $collector = app(CollectGroupInvoices::class);

        $invoices = $group
            ? $collector->handle($group, $this->year, $this->month)
            : collect();

        return [
            'company' => app(CurrentCompany::class)->get(),
            // An inactive group stays on the list while it is the one selected,
            // or the field would show a different group than the screen does.
            'groups' => PartnerGroup::query()
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhere('id', $this->groupId ?: 0))
                ->orderBy('name')
                ->get(),
            'group' => $group,
            'invoices' => $invoices,
            'excluded' => $group
                ? $collector->excluded($group, $this->year, $this->month)
                : collect(),
            'totals' => $invoices->groupBy('currency')->map(fn ($rows) => (float) $rows->sum('total')),
            'isLarge' => $invoices->count() > self::LARGE_BUNDLE,
            'periodLabel' => PeriodLabel::for($this->year, $this->month),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Objedinjena pošiljka</flux:heading>
            <flux:subheading>
                Jedan PDF sa svim izdatim fakturama grupe za period{{ $company ? ' — '.$company->displayName() : '' }}
            </flux:subheading>
        </div>

        <flux:button icon="user-group" :href="route('partner-groups.index')" wire:navigate>Grupe partnera</flux:button>
    </div>

    @if (! $company)
        <flux:callout icon="building-office" class="mt-6">
            <flux:callout.heading>Nije izabrano pravno lice</flux:callout.heading>
            <flux:callout.text>Izaberi pravno lice u bočnoj traci.</flux:callout.text>
        </flux:callout>
    @else
        @if (session('status'))
            <flux:callout icon="check-circle" color="lime" class="mt-6">
                <flux:callout.text>{{ session('status') }}</flux:callout.text>
            </flux:callout>
        @endif

        @error('bundle')
            <flux:callout icon="exclamation-triangle" color="red" class="mt-6">
                <flux:callout.text>{{ $message }}</flux:callout.text>
            </flux:callout>
        @enderror

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <flux:select class="max-w-xs" wire:model.live="groupId">
                <flux:select.option value="" :selected="$groupId === ''">Izaberi grupu</flux:select.option>
                @foreach ($groups as $groupOption)
                    <flux:select.option value="{{ $groupOption->id }}"
                        :selected="(string) $groupOption->id === $groupId">
                        {{ $groupOption->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-1">
                <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previousMonth" />
                <span class="min-w-40 text-center font-medium">{{ $periodLabel }}</span>
                <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="nextMonth" />
            </div>

            @if ($month)
                <flux:button size="sm" variant="ghost" wire:click="showWholeYear">Cela godina</flux:button>
            @endif
        </div>

        @if (! $group)
            <flux:callout icon="user-group" class="mt-6">
                <flux:callout.heading>Izaberi grupu</flux:callout.heading>
                <flux:callout.text>
                    Pošiljka nosi izdate fakture članova grupe za izabrani period,
                    svaku na svojoj strani, iza zajedničke rekapitulacije.
                </flux:callout.text>
            </flux:callout>
        @else
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <flux:badge color="zinc">{{ $invoices->count() }} u pošiljci</flux:badge>
                @foreach ($totals as $currency => $amount)
                    <flux:badge color="lime">{{ number_format($amount, 2, ',', '.') }} {{ $currency }}</flux:badge>
                @endforeach
                @if ($excluded->isNotEmpty())
                    <flux:badge color="amber">izostavljeno: {{ $excluded->count() }}</flux:badge>
                @endif

                <div class="ms-auto flex gap-2">
                    @if ($invoices->isNotEmpty())
                        <flux:button size="sm" icon="arrow-down-tray"
                            :href="route('partner-groups.pdf', ['partnerGroup' => $group, 'god' => $year, 'mes' => $month])"
                            target="_blank">
                            Preuzmi PDF
                        </flux:button>

                        <flux:button size="sm" variant="primary" icon="envelope" wire:click="openSendModal"
                            :disabled="! $group->canReceiveMail()">
                            Pošalji
                        </flux:button>
                    @endif
                </div>
            </div>

            @unless ($group->canReceiveMail())
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-4">
                    <flux:callout.text>
                        Grupa nema e-mail adresu, pa se pošiljka može samo preuzeti.
                        Adresa se unosi na formularu grupe.
                    </flux:callout.text>
                </flux:callout>
            @endunless

            @if ($isLarge)
                <flux:callout icon="exclamation-triangle" color="amber" class="mt-4">
                    <flux:callout.text>
                        Pošiljka ima {{ $invoices->count() }} faktura, pa prilog može biti prevelik
                        za poštanski server primaoca. Razmotri slanje po delovima — na primer,
                        preuzmi PDF i pošalji ga u više poruka.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="mt-4">
                @if ($invoices->isEmpty())
                    <flux:callout icon="document-text">
                        <flux:callout.heading>Nema izdatih faktura u ovom periodu</flux:callout.heading>
                        <flux:callout.text>
                            U pošiljku ulaze samo izdate fakture. Izdaj nacrte ili promeni period.
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Broj</flux:table.column>
                            <flux:table.column>Primalac</flux:table.column>
                            <flux:table.column>Izdato</flux:table.column>
                            <flux:table.column>Dospeva</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column align="end">Iznos</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($invoices as $invoice)
                                <flux:table.row :key="$invoice->id">
                                    <flux:table.cell variant="strong">
                                        <flux:link :href="route('invoices.show', $invoice)" wire:navigate>
                                            {{ $invoice->displayNumber() }}
                                        </flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell class="whitespace-normal">{{ $invoice->partner->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $invoice->issue_date->format('d.m.Y.') }}</flux:table.cell>
                                    <flux:table.cell>{{ $invoice->due_date->format('d.m.Y.') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$invoice->status->color()">
                                            {{ $invoice->status->label() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="end" variant="strong">
                                        {{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>

            @if ($excluded->isNotEmpty())
                <div class="mt-8">
                    <flux:heading size="lg">Izostavljeno iz pošiljke</flux:heading>
                    <flux:subheading>Ovi dokumenti postoje u periodu, ali ne ulaze u objedinjeni PDF.</flux:subheading>

                    <flux:table class="mt-4">
                        <flux:table.columns>
                            <flux:table.column>Dokument</flux:table.column>
                            <flux:table.column>Primalac</flux:table.column>
                            <flux:table.column>Razlog</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($excluded as $row)
                                <flux:table.row :key="'x-'.$row['invoice']->id">
                                    <flux:table.cell variant="strong">
                                        <flux:link :href="route('invoices.show', $row['invoice'])" wire:navigate>
                                            {{ $row['invoice']->displayNumber() }}
                                        </flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell class="whitespace-normal">{{ $row['invoice']->partner->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['reason'] }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif

            <flux:modal wire:model="showSendModal" class="max-w-lg">
                <form wire:submit="send" class="space-y-4">
                    <div>
                        <flux:heading size="lg">Slanje pošiljke</flux:heading>
                        <flux:subheading>
                            {{ $group->name }} — {{ $periodLabel }}, {{ $invoices->count() }} faktura u jednom prilogu.
                        </flux:subheading>
                    </div>

                    <flux:input wire:model="recipient" type="email" label="E-mail primaoca" required />

                    <flux:textarea wire:model="messageBody" label="Poruka (opciono)" rows="4"
                        placeholder="Ako ostane prazno, šalje se podrazumevani tekst." />

                    <div class="flex justify-end gap-2">
                        <flux:button variant="ghost" wire:click="$set('showSendModal', false)" type="button">
                            Odustani
                        </flux:button>
                        <flux:button variant="primary" type="submit">Pošalji</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    @endif
</section>
