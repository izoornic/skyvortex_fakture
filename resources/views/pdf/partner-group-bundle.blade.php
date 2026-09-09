@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
    $logo = $company->logoDataUri();

    // 1 faktura, 2–4 fakture, 5+ faktura.
    $invoiceWord = function (int $count): string {
        $tens = $count % 100;
        $ones = $count % 10;

        if ($ones === 1 && $tens !== 11) {
            return 'faktura';
        }

        return in_array($ones, [2, 3, 4], true) && ! in_array($tens, [12, 13, 14], true)
            ? 'fakture'
            : 'faktura';
    };
@endphp
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <title>Objedinjena pošiljka — {{ $group->name }} — {{ $periodLabel }}</title>
    <style>
        @page { margin: 18mm 14mm; }
@include('pdf.partials.invoice-styles')
        /* Every invoice starts a new sheet; the cover keeps the first one. */
        .document { page-break-before: always; }
        .cover .list th { background: #f4f4f5; border-bottom: 1px solid #d4d4d8; padding: 6px 5px; font-size: 8.5pt; text-align: left; }
        .cover .list td { border-bottom: 1px solid #e4e4e7; padding: 6px 5px; }
        .cover .sum td { border-top: 2px solid #18181b; font-weight: bold; font-size: 11.5pt; padding-top: 6px; }
    </style>
</head>
<body>

<div class="cover">
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
                </div>
            </td>
            <td class="right" style="width:40%">
                <div class="title">Objedinjena pošiljka</div>
                <div style="font-size:13pt; font-weight:bold">{{ $periodLabel }}</div>
                <div class="small muted">{{ count($documents) }} {{ $invoiceWord(count($documents)) }}</div>
            </td>
        </tr>
    </table>

    <table class="parties" style="margin-top:16px">
        <tr>
            <td>
                <div class="box">
                    <h3>Primalac pošiljke</h3>
                    <div class="name">{{ $group->name }}</div>
                    <div class="small">
                        @if ($group->contact_person)
                            {{ $group->contact_person }}<br>
                        @endif
                        {{ $group->email }}
                        @if ($group->phone)
                            <br>{{ $group->phone }}
                        @endif
                    </div>
                </div>
            </td>
            <td>
                <div class="box">
                    <h3>Period</h3>
                    <div class="name">{{ $periodLabel }}</div>
                    <div class="small muted">
                        U pošiljci su izdate fakture članova grupe za navedeni period.
                        Svaka faktura glasi na svog primaoca i plaća se posebno,
                        po svom pozivu na broj.
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="list" style="margin-top:14px">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:17%">Broj</th>
                <th style="width:38%">Primalac</th>
                <th style="width:13%">Izdato</th>
                <th style="width:13%">Dospeva</th>
                <th style="width:14%" class="right">Iznos</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoices as $invoice)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $invoice->displayNumber() }}</strong></td>
                    <td>{{ $invoice->partner->name }}</td>
                    <td>{{ $invoice->issue_date->format('d.m.Y.') }}</td>
                    <td>{{ $invoice->due_date->format('d.m.Y.') }}</td>
                    <td class="right">{{ $money($invoice->total) }} {{ $invoice->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top:12px">
        @foreach ($totals as $currency => $amount)
            <tr class="{{ $loop->first ? 'sum' : '' }}">
                <td class="right">Ukupno {{ $currency }}</td>
                <td class="right" style="width:30%">{{ $money($amount) }} {{ $currency }}</td>
            </tr>
        @endforeach
        @if (count($totals) > 1 || ! array_key_exists('RSD', $totals))
            <tr>
                <td class="right small muted">Protivvrednost u dinarima</td>
                <td class="right small muted" style="width:30%">{{ $money($totalRsd) }} RSD</td>
            </tr>
        @endif
    </table>

    <div class="footer small muted">
        Pošiljku je pripremio {{ $company->name }}. Za sva pitanja o pojedinačnoj fakturi
        obratite se izdavaocu na {{ $company->email ?: $company->phone }}.
    </div>
</div>

@foreach ($documents as $document)
    <div class="document">
        @include('pdf.partials.invoice-body', $document)
    </div>
@endforeach

</body>
</html>
