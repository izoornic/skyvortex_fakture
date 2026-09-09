---
paths:
  - 'app/Actions/**'
---

# Actions

## SVG se čisti pri unosu, ne pri izdavanju
SVG je dokument, ne slika: nosi `<script>`, `on*` atribute, `<foreignObject>`, animacije koje prepisuju `href` i spoljne reference.

Svaki uploadovan SVG mora proći kroz `App\Support\SvgSanitizer` PRE upisa na disk — na disku sme da stoji samo očišćen fajl. Nikad ne oslanjaj se na filtriranje pri izdavanju.

Tip fajla se utvrđuje po sadržaju (`SvgSanitizer` za SVG, `getimagesizefromstring()` za raster), ne po ekstenziji — ekstenzija je klijentova reč. Vidi `App\Actions\Companies\StoreCompanyLogo`.
