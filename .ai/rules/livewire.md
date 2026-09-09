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
