---
paths:
  - 'app/Actions/Invoices/**'
---

# Invoices

## Osnov oslobođenja se spušta sa firme, ne bira se po fakturi
Osnov oslobođenja od PDV-a (`vat_exemption_reason_id`) stoji na `companies` i odatle se spušta: formular fakture uzima ga kao podrazumevanog, `SaveInvoiceItems` upisuje ga na svaku stavku čija kategorija traži osnov, a osnov izabran na stavci ima prednost.

Razlog: polje koje se bira ručno na svakoj fakturi ostane prazno. Pre ove promene sve fakture firme van sistema PDV-a imale su NULL, PDF nije štampao član zakona, a SEF bi ih odbio na BT-120 / BT-121.

Koje kategorije traže osnov odlučuje `VatCategory::requiresExemptionReason()` — nemoj to ponovo ispisivati u upitu (`IssueInvoice` gradi listu iz enuma). `IssueInvoice` odbija izdavanje dokumenta sa stavkom bez PDV-a i bez osnova.

EN 16931 traži osnov po PDV kategoriji, ne jednom po dokumentu — zato `Invoice::exemptionReasons()` vraća sve osnove dokumenta, svaki po jednom, i PDF ih tako štampa.
