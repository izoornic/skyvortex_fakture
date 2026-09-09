<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->type->label() }} {{ $invoice->displayNumber() }}</title>
    <style>
        @page { margin: 18mm 14mm; }
@include('pdf.partials.invoice-styles')
    </style>
</head>
<body>

@include('pdf.partials.invoice-body')

</body>
</html>
