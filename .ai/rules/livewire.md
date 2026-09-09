---
paths:
  - 'resources/views/livewire/**'
---

# Livewire

## Nikad ne imenuj petlju u Blade-u kao public property komponente
Blade `@foreach` promenljiva ostaje u dosegu i posle petlje i pregazi istoimeni public property komponente do kraja šablona.

`@foreach ($currencies as $currency)` u formularu fakture je zbog toga ispisivao ceo Currency model kao JSON umesto vrednosti `public string $currency`, a `@if ($currency !== 'RSD')` je uvek bio tačan.

Petlje imenuj sa sufiksom: `$currencyOption`, `$partnerOption`. U `:selected` poređenju koristi `$this->currency` (property), ne golu promenljivu.

## temporaryUrl() na odbijenom fajlu ruši ceo formular
Posle neuspele validacije fajl ostaje u property-ju, a `$file->temporaryUrl()` baca `FileNotPreviewableException` za sve što nije u `livewire.temporary_file_upload.preview_mimes` — korisnik umesto poruke o grešci dobije 500.

Pregled uvek računaj kroz čuvar, npr. u `with()`:
`$this->logo instanceof TemporaryUploadedFile && $this->logo->isPreviewable() ? $this->logo->temporaryUrl() : null`.

Primer: `resources/views/livewire/companies/form.blade.php` (metoda `logoPreview()`).

## flux:select — opcije traže :selected, prazna opcija nikad disabled
Obe greške ne prijavljuju ništa: ekran i server prosto prikazuju različite vrednosti, pa se upiše pogrešan podatak ili se odbije ispravan.

Opcije moraju imati `:selected` vezan za stanje komponente. Livewire pri svakom krugu ponovo iscrtava komponentu; bez `selected` pretraživač pada na prvu opciju dok server drži pravu vrednost — stavka sa 10% PDV-a se tiho snimila kao 20%.

Prazna (placeholder) opcija ne sme biti `disabled`. Pretraživač ne može da drži onemogućenu opciju kao izabranu, pa prikaže prvu pravu; korisnik vidi vrednost, ne menja je, `change` se ne okine i do servera ne stigne ništa — otuda „The partner id field is required" na partneru koji je vidno izabran. Ni Flux-ov `placeholder=` prop ne koristi: on iscrtava `disabled selected` i duplira praznu opciju koju šablon već ima.

Testovi: `tests/Feature/Invoices/InvoiceFormSelectStateTest.php` kodira oba pravila — `browserValue()` računa vrednost koju bi pretraživač zaista prikazao i poredi je sa stanjem komponente; taj pomoćnik prepiši za svaki novi formular sa padajućim listama. `Volt::test()` preskače hidraciju i ne hvata greške na nivou wire-a; za pravi `/livewire/update` krug koristi `tests/Feature/Invoices/InvoiceFormHttpRoundTripTest.php` kao obrazac.
