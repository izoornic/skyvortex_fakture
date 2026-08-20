<x-mail::message>
# {{ $invoice->type->label() }} {{ $invoice->displayNumber() }}

@if ($body)
{{ $body }}
@else
U prilogu Vam dostavljamo {{ mb_strtolower($invoice->type->label()) }} broj **{{ $invoice->displayNumber() }}**.
@endif

**Iznos za uplatu:** {{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}
**Rok plaćanja:** {{ $invoice->due_date->format('d.m.Y.') }}
@if ($invoice->fullPaymentReference())
**Poziv na broj:** {{ $invoice->fullPaymentReference() }}
@endif

Srdačan pozdrav,<br>
{{ $company->name }}
</x-mail::message>
