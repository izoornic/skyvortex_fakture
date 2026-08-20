# Changelog

Sve značajne izmene na projektu **SkyVortex Fakture** beleže se u ovom fajlu.

Format prati [Keep a Changelog](https://keepachangelog.com/), a verzionisanje
[Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`.

- **MAJOR** — izmena koja lomi kompatibilnost (npr. promena šeme baze bez migracije unazad)
- **MINOR** — nova funkcionalnost, kompatibilna unazad
- **PATCH** — ispravke grešaka i sitna doterivanja

Aktuelna verzija se drži u [`config/global.php`](config/global.php) pod ključem `version`
i mora se ručno uskladiti sa poslednjim izdanjem u ovom fajlu.

Tipovi izmena: `Dodato`, `Izmenjeno`, `Zastarelo`, `Uklonjeno`, `Ispravljeno`, `Bezbednost`.

Naslov izdanja ima oblik `## [X.Y.Z] — YYYY-MM-DD — grana`, gde je `grana` git grana
na koju je izdanje commitovano (npr. `main`). Izdanje 1.0.0 nema granu jer projekat
tada još nije bio pod verzionom kontrolom.

---

## [Neizdato]

### Dodato

- `CLAUDE.md`: sekcija `=== changelog rules ===` — pravilo koje obavezuje da svaka
  izmena bude upisana u `CHANGELOG.md` pod `[Neizdato]`, sa postupkom za izdavanje
  nove verzije i obaveznim usklađivanjem sa `version` u `config/global.php`.
  ⚠️ Zajedno sa sekcijom `=== local environment rules ===`, ovo je ručno dodat blok
  koji `php artisan boost:install` briše — posle svakog pokretanja treba ga vratiti.
- `docs/plan-razvoja.md` — plan razvoja aplikacije: opseg faze 1 i faze 2 (SEF),
  model podataka, tehničke odluke, faze isporuke M0–M9, rizici i otvorena pitanja.
- **M0 — temelji pristupa.** Uloge, dodela pravnih lica i višekompanijski scoping:
  - `role` na `users` (`admin` / `knjigovodja`) preko enuma `App\Enums\UserRole`, uz
    `company_user` pivot za dodelu pravnih lica knjigovođi.
  - Tabele `companies`, `bank_accounts`, `invoice_number_sequences` i `audit_logs`.
  - `App\Support\CurrentCompany` drži aktivno pravno lice u sesiji i uvek ga proverava
    kroz dozvoljena pravna lica korisnika, pa izmenjena sesija ne može proširiti pristup.
  - `App\Models\Scopes\CompanyScope` i trait `BelongsToCompany` ograničavaju svaki upit
    nad podacima pravnog lica; `acrossCompanies()` je svesni izlaz za izveštaje i konzolu.
  - Politike `CompanyPolicy`, `BankAccountPolicy` i `UserPolicy` — pravna lica i korisnike
    uređuje samo admin, knjigovođa ih vidi i koristi.
  - Trait `Auditable` i model `AuditLog` beleže ko je šta promenio, bez lozinki i tokena.
  - Enum `App\Enums\DocumentType` nosi prefikse i šablon broja (`2026-0001`, `AV-`, `KO-`,
    `KZ-`, `PR-`); brojač se pri unosu firme postavlja na poslednji broj iskorišćen van
    aplikacije.
  - Ekrani: lista i formular pravnih lica sa bankovnim računima, lista i formular
    korisnika sa dodelom pravnih lica, prekidač aktivnog pravnog lica u bočnoj traci.
  - Seeder pravi `admin@skyvortex.test` i `knjigovodja@skyvortex.test` (lozinka `password`),
    tri pravna lica i dodelu samo prvog knjigovođi.
  - 38 novih testova pokriva scoping, prekidač, numeraciju, bankovne račune, politike
    pristupa i revizioni trag. Ukupno 65 testova prolazi.
- **M1 — šifarnici i partneri.**
  - Tabela `partners` sa sva četiri tipa preko enuma `App\Enums\PartnerType`
    (pravno lice, preduzetnik, fizičko lice, strano lice). Formular menja obavezna
    polja prema tipu i briše identifikatore koji za novi tip nemaju smisla.
  - **JMBG se čuva šifrovano** (`encrypted` cast), ne ulazi u revizioni trag i
    skriven je pri serijalizaciji.
  - `App\Support\PartnerRules` je jedina definicija ispravnog partnera, koju dele
    formular i uvoz. Tu je i pravilo da izdavalac ne može biti partner sam sebi,
    provereno po PIB-u.
  - Isti PIB je jedinstven unutar jednog pravnog lica, ali sme postojati kod više njih.
  - Šifarnici `currencies`, `units_of_measure`, `vat_rates` i `vat_exemption_reasons`,
    zajednički za sva pravna lica; čita ih svako, uređuje ih admin (gate `manage-codebooks`).
  - `ReferenceDataSeeder` puni valute, 20 jedinica mere po UN/ECE Rec 20 i PDV stope
    (20%, 10%, 0%); može se pokretati više puta bez dupliranja.
  - PDV stope imaju period važenja, pa stara faktura zadržava stopu koja je važila
    na datum prometa. Enum `App\Enums\VatCategory` nosi šifre po UNTDID 5305 / EN 16931,
    koje traži UBL u fazi 2.
  - `App\Actions\ImportPartners` uvozi partnere iz CSV-a: prepoznaje razdvajač
    (tačka-zarez ili zarez), mapira zaglavlje na srpske nazive kolona, validira red po red,
    prijavljuje neispravne redove umesto da prekine uvoz, i po izboru ažurira postojeće
    partnere prepoznate po PIB-u.
  - 31 nov test. Ukupno 96 testova prolazi.
- **M2 — fakture.** Jezgro sistema: dokument sa stavkama od nacrta do izdavanja i PDF-a.
  - Tabele `invoices` i `invoice_items`. Broj se dodeljuje tek pri izdavanju, pa nacrt
    ne zauzima broj koji možda neće iskoristiti.
  - `App\Actions\Invoices\GenerateInvoiceNumber` dodeljuje broj uz `lockForUpdate` i
    odbija da radi van transakcije, jer zaključavanje van nje ništa ne znači.
  - `App\Support\PaymentReference` računa poziv na broj po modelu 97
    (ISO 7064 MOD 97-10). Hvata svaku izmenu jedne cifre u referenci.
  - `App\Support\InvoiceTotals` računa iznose po stavci pa sabira po PDV kategoriji i
    stopi — tako se štampana rekapitulacija slaže sa ukupnim iznosom.
  - Tok: nacrt → izdata → stornirana. Izdata faktura se ne menja; `IssueInvoice` odbija
    dokument bez stavki i dokument u stranoj valuti bez kursa.
  - `CancelInvoice` beleži ko je, kada i zašto stornirao, i **zadržava broj** — praznina
    u nizu bila bi teža za objašnjenje od storna.
  - Strana valuta: kurs se unosi ručno, a protivvrednost u RSD se čuva uz dokument da je
    izveštaji u M6 ne moraju ponovo računati.
  - Mesečni pregled sa kretanjem po mesecima, filterima i zbirom prometa.
  - PDF preko `barryvdh/laravel-dompdf`, sa ugrađenim DejaVu fontom zbog naših slova;
    slanje mejlom sa PDF-om u prilogu preko `App\Mail\InvoiceMail`.
  - 43 nova testa. Ukupno 139 testova prolazi.

### Izmenjeno

- Format naslova izdanja: iza datuma se sada dodaje i git grana
  (`## [X.Y.Z] — YYYY-MM-DD — grana`). Dokumentovano u zaglavlju ovog fajla i
  u pravilu `=== changelog rules ===` u `CLAUDE.md`.
- `docs/plan-razvoja.md`: preciziran ulaz bankarskih izvoda. Elektronski izvodi su svi
  u XML formatu, a papirni se unose ručno; oba toka idu kroz isto uparivanje.
  Dodato polje `source` u `bank_statements`, dopunjen M4 i preformulisan rizik 1 —
  otvoreno pitanje više nije format, nego šema XML-a po banci.
- `docs/plan-razvoja.md`: donete tri odluke koje su blokirale M2 — format broja fakture
  (`2026-0001`, brojač po pravnom licu / tipu dokumenta / godini, sa početnom vrednošću
  koja se zadaje pri unosu firme), storniranje dozvoljeno svim ulogama bez odobrenja,
  i ručni unos kursa bez spoljnog servisa. Rizik oko izvora kursne liste je time zatvoren,
  a uvedena su dva nova: greška u ručno unetom kursu i storno bez odobrenja.
- `docs/plan-razvoja.md`: zatvorene i poslednje dve odluke pred M2 — ostali tipovi
  dokumenata dobijaju prefiks u broju (`AV-`, `KO-`, `KZ-`, `PR-`), svaki sa sopstvenim
  brojačem, dok faktura ostaje `2026-0001`; isto pravno lice ne može biti i izdavalac
  i partner na istoj fakturi — firma ne fakturiše samoj sebi, ali dve firme iz sistema
  smeju međusobno da fakturišu.

### Ispravljeno

- Lista pravnih lica na `/companies` prikazivala je samo `name`, dok prekidač u bočnoj
  traci i svi ostali ekrani prikazuju `displayName()`, tj. `short_name` kada postoji.
  Ista firma se zato na dva mesta zvala različito i nije se mogla povezati. Lista sada
  prikazuje i skraćeni naziv, kada se razlikuje od punog.
- `CompanyFactory` je za `name` i `short_name` generisala dva nepovezana nasumična naziva,
  pa je u seed podacima jedna firma delovala kao dve. Skraćeni naziv se sada izvodi iz punog.
- **Padajuće liste gubile su izabranu vrednost pri svakom osvežavanju.** Nijedan
  `<option>` nije nosio `selected`, pa je Livewire posle round-tripa vraćao pregledaču
  listu bez izbora. Gde postoji placeholder, pregledač bi se vratio na njega; gde ga
  nema, na prvu opciju. Server je čuvao pravu vrednost, ekran je pokazivao drugu, a pri
  slanju forme odlazilo je ono što piše na ekranu.
  - Prijavljena posledica: izabrani partner na `/fakture/nova` vraćao je grešku
    „The partner id field is required".
  - Neprijavljena, teža posledica: stavka sa 10% PDV-a mogla je da se sačuva sa 20%,
    jer se stopa nečujno vraćala na prvu ponuđenu. Isto je važilo za jedinicu mere,
    PDV kategoriju, valutu, račun za uplatu, vrstu dokumenta, tip partnera i uloge.
  - Sve `flux:select.option` liste sada iscrtavaju `selected` prema stanju na serveru,
    a placeholder partnera je izabran samo dok ništa nije izabrano.
  - `InvoiceFormHttpRoundTripTest` vozi formular kroz stvarni `/livewire/update` krug,
    sa vrednostima kakve `<select>` zaista šalje. `Volt::test()` poziva komponentu
    direktno i preskoči hidraciju, pa ne može da uhvati grešku koja postoji samo na žici.

---

## [1.0.0] — 2026-08-20

Inicijalno postavljanje projekta.

### Dodato

- Laravel 12.67 skeleton sa **Livewire Starter Kit**-om (Livewire 4.4, Volt 1.11, Flux UI 2.17,
  Tailwind CSS 4, Vite 6) i kompletnim auth scaffolding-om: prijava, registracija,
  reset lozinke, verifikacija mejla i podešavanja naloga (profil / lozinka / izgled).
- MySQL baza `skyvortex_fakture` (utf8mb4 / utf8mb4_unicode_ci) sa osnovnim migracijama:
  `users`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
  `password_reset_tokens`, `migrations`.
- `config/global.php` — projektne konstante: `version`, `siteFooter`, `paginate` (20).
- **Laravel Boost** 2.5 (dev) — MCP server (`.mcp.json`), AI smernice (`CLAUDE.md`)
  i 5 skills paketa u `.claude/skills/`.
- Seeder kreira nalog za razvoj: `test@example.com` / `password`.
- Ovaj `CHANGELOG.md`.

### Izmenjeno

- `.env` / `.env.example`: `APP_NAME="SkyVortex Fakture"`, `APP_URL=http://skyvortex_fakture.test`,
  `APP_LOCALE=sr`, `APP_FAKER_LOCALE=sr_RS`, baza prebačena sa SQLite na MySQL
  (`127.0.0.1:3306`, korisnik `root`).
- `CLAUDE.md`: Boost-ova automatski generisana **Laravel Herd** sekcija zamenjena je
  sekcijom za **Laragon**, jer projekat servira Laragonov Apache na
  `http://skyvortex_fakture.test`, a ne Herd.
  ⚠️ Ponovno pokretanje `php artisan boost:install` vraća Herd verziju — izmenu treba
  ponovo primeniti.

### Ispravljeno

- Volt stranice u `settings/*` padale su sa `No hint path defined for [layouts]`.
  Starter kit je pisan za Livewire 3, a Composer je povukao Livewire 4, čiji je
  podrazumevani `component_layout` postavljen na `layouts::app` umesto na
  `components.layouts.app`. Objavljen je `config/livewire.php` sa ispravnom vrednošću.
  Testovi: 27/27 prolazi.
