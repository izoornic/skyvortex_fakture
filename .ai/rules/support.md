---
paths:
  - 'app/Support/**'
  - app/Support/IpsQrPayload.php
---

# Support

## dompdf pogrešno skalira SVG simbole — razreši `<use>` pri unosu
php-svg-lib (koji dompdf koristi za SVG) skalira `<symbol>` prema korenskom viewport-u umesto prema `width`/`height` sa `<use>`. Logo od pločica sa `viewBox="0 0 1.09 1.09"` u dokumentu visine 88.55 dobio je faktor 81.24 — mastilo je prekrilo celu stranu, a ništa ne seče SVG na okvir slike.

Zato `App\Support\SvgSymbolInliner` pri unosu prepisuje svaki `<use>` u `<g transform="…">` sa stvarnom geometrijom.

Merenje veličine slike u PDF-u iz matrice postavljanja NE otkriva ovo — okvir izgleda uredno dok crtež beži van papira. Meri opseg samog mastila, kroz stek transformacija (vidi `logoInkSize()` u `InvoicePdfTest`).

## NBS IPS QR: format koji banka odbija ako je promašen
Polja se spajaju uspravnom crtom kao `TAG:vrednost`. Dve stvari odlučuju da li banka prihvata kôd:

1. Iznos: `RSD105960,00` — zarez kao decimalni znak, bez separatora hiljada, uvek dve decimale.
2. `RO`: model se lepi uz poziv na broj bez razmaka (`9759-20260015`).

Vrednosti moraju biti očišćene od `|` i preloma reda pre upisa, i sečene na dužine iz standarda (N i P 70, S 35, RO 35) — predugo polje ruši ceo kôd, ne samo to polje.

Kôd se ne pravi za nacrt, stranu valutu, niti kad račun nema 18 cifara.

## NBS IPS QR: crtica u modelu 97 i ćirilica ruše ceo kôd
Potvrđeno na NBS validatoru sa stvarnim podacima, ne iz dokumentacije:

1. `RO` sa modelom 97 sme da nosi samo cifre i slova. Crtica je dozvoljena samo za ostale modele ili bez modela. Poziv na broj se štampa kao `97 53-20260017`, ali u kôd ide `975320260017`.
2. Ćirilica u `N` i `P` se odbija — validator ruši ceo kôd zbog jedne reči. Latinična dijakritika prolazi: `MILOŠ STEFANOVIĆ PR DOBAR KOMŠIJA` je prošao validaciju u tagu `P`. Zato se prepisuje samo pismo, ne i slova — `Čačak` ostaje `Čačak`.

Kontrolni broj po modelu 97 je: referenca + „00", mod 97, oduzeto od 98, kontrola ide na početak. `PaymentReference` to već radi ispravno.
