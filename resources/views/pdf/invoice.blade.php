@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->type->label() }} {{ $invoice->displayNumber() }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #18181b; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #71717a; }
        .right { text-align: right; }
        .center { text-align: center; }
        .small { font-size: 8.5pt; }
        .title { font-size: 16pt; font-weight: bold; }
        .parties td { vertical-align: top; width: 50%; padding: 0 6px 0 0; }
        .box { border: 1px solid #d4d4d8; padding: 8px 10px; }
        .box h3 { margin: 0 0 4px; font-size: 8.5pt; text-transform: uppercase; color: #71717a; letter-spacing: .04em; }
        .box .name { font-weight: bold; font-size: 11pt; }
        .meta td { padding: 1px 0; }
        .meta .label { color: #71717a; padding-right: 10px; }
        .items { margin-top: 14px; }
        .items th { background: #f4f4f5; border-bottom: 1px solid #d4d4d8; padding: 6px 5px; font-size: 8.5pt; text-align: left; }
        .items td { border-bottom: 1px solid #e4e4e7; padding: 6px 5px; }
        .items .desc { color: #71717a; font-size: 8.5pt; }
        .totals { margin-top: 12px; }
        .totals td { padding: 3px 5px; }
        .totals .grand td { border-top: 2px solid #18181b; font-weight: bold; font-size: 11.5pt; padding-top: 6px; }
        .recap th, .recap td { border-bottom: 1px solid #e4e4e7; padding: 4px 5px; font-size: 8.5pt; }
        .footer { margin-top: 26px; }
        .sign { margin-top: 34px; }
        .sign td { width: 50%; }
        .sign .line { border-top: 1px solid #a1a1aa; padding-top: 4px; width: 60%; }
        .cancelled { color: #dc2626; border: 2px solid #dc2626; padding: 4px 10px; font-weight: bold; display: inline-block; }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="width:60%">
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
        <p><strong>Izdavalac nije u sistemu PDV-a.</strong>
            @if ($invoice->vatExemptionReason)
                {{ $invoice->vatExemptionReason->description }}
                @if ($invoice->vatExemptionReason->legal_basis)
                    ({{ $invoice->vatExemptionReason->legal_basis }})
                @endif
            @endif
        </p>
    @elseif ($invoice->vatExemptionReason)
        <p>
            {{ $invoice->vatExemptionReason->description }}
            @if ($invoice->vatExemptionReason->legal_basis)
                ({{ $invoice->vatExemptionReason->legal_basis }})
            @endif
        </p>
    @endunless

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
            <div class="line small" style="margin-left:auto">Potpis i pečat</div>
        </td>
    </tr>
</table>

</body>
</html>
