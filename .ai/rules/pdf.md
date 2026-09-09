---
paths:
  - 'resources/views/pdf/**'
---

# Pdf

## Telo fakture je partial — menja se na jednom mestu
`pdf/invoice.blade.php` je samo omotač. Sam dokument je u `pdf/partials/invoice-body.blade.php`, a stilovi u `pdf/partials/invoice-styles.blade.php`; oba uključuje i `pdf/partner-group-bundle.blade.php`, pa strana u objedinjenoj pošiljci izgleda isto kao kad se faktura šalje sama.

Izmenu izgleda fakture radi u partialu, nikad u omotaču — inače pošiljka i pojedinačna faktura krenu da se razilaze.

Podatke sklapa `RenderInvoicePdf::viewData()` (uključujući logo i IPS QR), pa šablon ne razrešava servise. Testovi koji iscrtavaju `pdf.invoice` moraju da koriste tu metodu; ručno nabrojani ključevi obavezno propuste `logo` ili `ipsQr` i sruše prikaz.
