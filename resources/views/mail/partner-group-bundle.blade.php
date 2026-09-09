<x-mail::message>
# Fakture za {{ $periodLabel }}

@if ($body)
{{ $body }}
@else
U prilogu Vam dostavljamo objedinjeni dokument sa fakturama za {{ $periodLabel }},
izdatim na članove grupe **{{ $group->name }}**.
@endif

@foreach ($invoices as $invoice)
- **{{ $invoice->displayNumber() }}** — {{ $invoice->partner->name }} — {{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }} (dospeva {{ $invoice->due_date->format('d.m.Y.') }})
@endforeach

Svaka faktura se plaća posebno, po svom pozivu na broj, koji je naveden na samoj fakturi.

Srdačan pozdrav,<br>
{{ $company->name }}
</x-mail::message>
