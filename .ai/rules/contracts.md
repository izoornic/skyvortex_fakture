---
paths:
  - 'app/Actions/Contracts/**'
---

# Contracts

## Ugovori: period se računa iz dana obračuna, važenje se proverava prema periodu
Obračun je vezan za DAN, ne za period: dva ugovora iste firme mogu 1. aprila fakturisati različite mesece, jer svaki nosi svoj `billing_mode` (`unazad` → mesec pre, `unapred` → tekući mesec).

Važenje ugovora (`starts_on` / `ends_on`) proverava se prema **periodu koji se fakturiše**, nikad prema danu obračuna — inače ugovor istekao 31. marta ostaje bez martovske fakture, koja nastaje tek u aprilu. Pravilo živi u `Contract::coversPeriod()`; nemoj ga duplirati u upitu.

Dupli obračun sprečava `last_generated_year` + `last_generated_month` na ugovoru, ne provera postojanja fakture.
