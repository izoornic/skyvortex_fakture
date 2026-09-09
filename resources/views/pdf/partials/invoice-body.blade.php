@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
@endphp


<table>
    <tr>
        <td style="width:60%">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="logo">
            @endif
            <div class="title">{{ $company->name }}</div>
            <div class="small muted">
                {{ $company->address }}, {{ $company->postal_code }} {{ $company->city }}<br>
                PIB: {{ $company->pib }} &nbsp;·&nbsp; Matični broj: {{ $company->registration_number }}
                @if ($company->activity_code)
                    &nbsp;·&nbsp; Šifra delatnosti: {{ $company->activity_code }}
                @endif
                @if ($company->email || $company->phone)
                    <br>{{ $company->email }}@if ($company->email && $company->phone) &nbsp;·&nbsp; @endif{{ $company->phone }}
                @endif
            </div>
        </td>
        <td class="right" style="width:40%">
            <div class="title">{{ $invoice->type->label() }}</div>
            <div style="font-size:13pt; font-weight:bold">{{ $invoice->displayNumber() }}</div>
            @if ($invoice->status->value === 'stornirana')
                <div class="cancelled" style="margin-top:6px">STORNIRANO</div>
            @endif
        </td>
    </tr>
</table>

<table class="parties" style="margin-top:16px">
    <tr>
        <td>
            <div class="box">
                <h3>Izdavalac</h3>
                <div class="name">{{ $company->name }}</div>
                <div class="small">
                    {{ $company->address }}<br>
                    {{ $company->postal_code }} {{ $company->city }}<br>
                    PIB: {{ $company->pib }} · MB: {{ $company->registration_number }}
                </div>
            </div>
        </td>
        <td>
            <div class="box">
                <h3>Primalac</h3>
                <div class="name">{{ $partner->name }}</div>
                <div class="small">
                    {{ $partner->address }}<br>
                    {{ $partner->postal_code }} {{ $partner->city }}@if ($partner->country_code !== 'RS'), {{ $partner->country_code }}@endif<br>
                    @if ($partner->pib)
                        PIB: {{ $partner->pib }}@if ($partner->registration_number) · MB: {{ $partner->registration_number }}@endif
                    @elseif ($partner->vat_id)
                        VAT: {{ $partner->vat_id }}
                    @endif
                </div>
            </div>
        </td>
    </tr>
</table>

<table class="meta" style="margin-top:14px">
    <tr>
        <td style="width:50%">
            <table class="meta">
                <tr><td class="label">Datum izdavanja</td><td>{{ $invoice->issue_date->format('d.m.Y.') }}</td></tr>
                <tr><td class="label">Datum prometa</td><td>{{ $invoice->supply_date->format('d.m.Y.') }}</td></tr>
                <tr><td class="label">Rok plaćanja</td><td>{{ $invoice->due_date->format('d.m.Y.') }}</td></tr>
                <tr><td class="label">Obračunski period</td><td>{{ $invoice->periodLabel() }}</td></tr>
                @if ($invoice->place_of_issue)
                    <tr><td class="label">Mesto izdavanja</td><td>{{ $invoice->place_of_issue }}</td></tr>
                @endif
            </table>
        </td>
        <td style="width:50%">
            <table class="meta">
                @if ($account)
                    <tr><td class="label">Račun</td><td><strong>{{ $account->formattedAccountNumber() }}</strong></td></tr>
                    <tr><td class="label">Banka</td><td>{{ $account->bank_name }}</td></tr>
                @endif
                @if ($invoice->fullPaymentReference())
                    <tr><td class="label">Poziv na broj</td><td><strong>{{ $invoice->fullPaymentReference() }}</strong></td></tr>
                @endif
                <tr><td class="label">Valuta</td><td>{{ $invoice->currency }}</td></tr>
                @if ($invoice->isForeignCurrency())
                    <tr><td class="label">Kurs</td><td>{{ $money($invoice->exchange_rate) }} RSD</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:36%">Naziv</th>
            <th style="width:8%" class="right">Količina</th>
            <th style="width:8%">JM</th>
            <th style="width:13%" class="right">Cena</th>
            <th style="width:7%" class="right">Rabat</th>
            <th style="width:7%" class="right">PDV</th>
            <th style="width:17%" class="right">Vrednost</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    {{ $item->name }}
                    @if ($item->description)
                        <div class="desc">{{ $item->description }}</div>
                    @endif
                </td>
                <td class="right">{{ $qty($item->quantity) }}</td>
                <td>{{ $item->unit_symbol ?? $item->unit_code }}</td>
                <td class="right">{{ $money($item->unit_price) }}</td>
                <td class="right">{{ $item->discount_percent > 0 ? $money($item->discount_percent).'%' : '—' }}</td>
                <td class="right">{{ $money($item->vat_rate) }}%</td>
                <td class="right">{{ $money($item->line_subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td style="width:55%; vertical-align:top">
            @if ($recapitulation->count() > 1 || $invoice->vat_total > 0)
                <table class="recap">
                    <thead>
                        <tr>
                            <th>Kat.</th>
                            <th class="right">Stopa</th>
                            <th class="right">Osnovica</th>
                            <th class="right">PDV</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recapitulation as $row)
                            <tr>
                                <td>{{ $row['vat_category'] }}</td>
                                <td class="right">{{ $money($row['vat_rate']) }}%</td>
                                <td class="right">{{ $money($row['base']) }}</td>
                                <td class="right">{{ $money($row['vat']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($ipsQr)
                <table class="ips">
                    <tr>
                        <td style="width:98px"><img src="{{ $ipsQr }}" alt="NBS IPS QR"></td>
                        <td class="caption">
                            <strong>NBS IPS QR</strong><br>
                            Skenirajte kôd u mobilnoj aplikaciji banke<br>
                            i platite bez prekucavanja podataka.
                        </td>
                    </tr>
                </table>
            @endif
        </td>
        <td style="width:45%">
            <table class="totals">
                <tr>
                    <td>Osnovica</td>
                    <td class="right">{{ $money($invoice->subtotal) }} {{ $invoice->currency }}</td>
                </tr>
                @if ($invoice->discount_total > 0)
                    <tr>
                        <td>Rabat</td>
                        <td class="right">−{{ $money($invoice->discount_total) }} {{ $invoice->currency }}</td>
                    </tr>
                @endif
                <tr>
                    <td>PDV</td>
                    <td class="right">{{ $money($invoice->vat_total) }} {{ $invoice->currency }}</td>
                </tr>
                <tr class="grand">
                    <td>Za uplatu</td>
                    <td class="right">{{ $money($invoice->total) }} {{ $invoice->currency }}</td>
                </tr>
                @if ($invoice->isForeignCurrency())
                    <tr>
                        <td class="small muted">Protivvrednost</td>
                        <td class="right small muted">{{ $money($invoice->total_rsd) }} RSD</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<div class="footer small">
    @unless ($company->in_vat_system)
        <p><strong>Izdavalac nije u sistemu PDV-a.</strong></p>
    @endunless

    {{-- The basis is stated per VAT category, so a document that mixes them
         prints each one once. --}}
    @foreach ($exemptionReasons as $reason)
        <p>
            {{ $reason->description }}
            @if ($reason->legal_basis)
                ({{ $reason->legal_basis }})
            @endif
        </p>
    @endforeach

    @if ($invoice->note)
        <p>{{ $invoice->note }}</p>
    @endif

    @if ($invoice->status->value === 'stornirana')
        <p><strong>Dokument je storniran {{ $invoice->cancelled_at?->format('d.m.Y.') }}.</strong>
            @if ($invoice->cancel_reason) Razlog: {{ $invoice->cancel_reason }} @endif
        </p>
    @endif
</div>

<table class="sign">
    <tr>
        <td class="small muted">{{ config('global.siteFooter') }}</td>
        <td class="right">
            {{-- A document sent by mail carries no seal, so it says so instead
                 of leaving an empty line nobody will ever sign. --}}
            @if ($invoice->valid_without_signature)
                <div class="small" style="font-style:italic">
                    Ova faktura je validna u elektronskom obliku bez pečata i potpisa!
                </div>
            @else
                <div class="line small" style="margin-left:auto">Potpis i pečat</div>
            @endif
        </td>
    </tr>
</table>

