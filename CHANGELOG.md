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

- **Oznaka „validno bez pečata i potpisa".** Dokument koji ide mejlom nema ni pečat ni
  potpis, pa to mora da piše na njemu.
  - Migracija `2026_09_06_083430_add_valid_without_signature_to_invoices_and_contracts`
    dodaje `valid_without_signature` (boolean, podrazumevano `false`) na `invoices`
    i `contracts`.
  - Polje za štikliranje na formularu fakture i na formularu ugovora. Ugovor je prenosi
    na svaki nacrt (`GenerateContractDrafts`), a prenosi je i „Kopiraj u naredni mesec"
    (`CopyInvoiceToNextPeriod`). Oba modela nose `valid_without_signature => false` u
    `$attributes`, jer podrazumevana vrednost iz baze ne postoji na modelu koji nije
    ponovo učitan — nacrt iz neoznačenog ugovora bi inače upisivao NULL.
  - Na ekranu fakture se vidi oznaka, jer se izdati dokument više ne otvara u formularu.
  - **Oznaka je uključena po podrazumevanom izboru** — dokumenti odlaze mejlom, pa je
    pravilo, a ne izuzetak; skida se ručno. Migracija
    `2026_09_06_084634_change_valid_without_signature_default_to_true` menja podrazumevanu
    vrednost kolone na obe tabele i uključuje oznaku na postojećim ugovorima i nacrtima.
    Izdati dokumenti se ne diraju — kako se štampa već poslat dokument ne sme da se
    promeni pod njim.
  - Kada je uključena, `resources/views/pdf/partials/invoice-body.blade.php` umesto crte
    za potpis štampa „Ova faktura je validna u elektronskom obliku bez pečata i potpisa!" —
    prazna crta koju niko neće potpisati protivreči samoj oznaci.
  - 8 novih testova: `tests/Feature/Invoices/ValidWithoutSignatureTest.php`.

- **M3a — grupe partnera i objedinjena pošiljka.** Upravnik koji drži deset stambenih
  zajednica dobija jedan mejl sa jednim PDF-om, a svaka faktura i dalje glasi na svoju
  zajednicu, sa svojim brojem, pozivom na broj i IPS QR kodom.
  - Tabela `partner_groups` (naziv, `email`, kontakt, telefon, napomene, `is_active`) sa
    `company_id` scoping-om kao i ostali podaci pravnog lica, i `partners.partner_group_id`
    (`nullOnDelete`) — partner je u najviše jednoj grupi ili ni u jednoj. Brisanje grupe
    ne briše partnere, samo im uklanja kanal isporuke.
    Migracije `2026_09_06_080759_create_partner_groups_table` i
    `2026_09_06_080800_add_partner_group_to_partners_table`.
  - Model `App\Models\PartnerGroup` (revizioni trag, `search` i `active` scope,
    `canReceiveMail()`), politika `PartnerGroupPolicy` — grupe održavaju obe uloge,
    unutar pravnih lica do kojih smeju. Grupa sa članovima se ne briše nego isključuje.
  - Naziv grupe je jedinstven unutar pravnog lica, a kod drugog pravnog lica je slobodan.
  - `App\Actions\PartnerGroups\CollectGroupInvoices` je jedina definicija šta ulazi u
    pošiljku: izdate fakture partnera grupe za period (izdata, delimično plaćena, plaćena).
    Nacrti i stornirane se izostavljaju, a `excluded()` vraća i razlog za svaku, da ekran
    može da kaže zašto faktura koju korisnik vidi nije u dokumentu.
  - `App\Actions\PartnerGroups\RenderPartnerGroupBundlePdf` iscrtava jedan PDF:
    naslovna rekapitulacija (grupa, period, spisak faktura sa iznosima, zbir po valuti i
    protivvrednost u RSD), pa svaka faktura na svojoj strani. Fakture se **crtaju, ne
    spajaju** — dompdf ne ume da spoji gotove PDF-ove, a spajanje bi tražilo
    `setasign/fpdi`. Podaci svake fakture se sklapaju pre iscrtavanja, pa greška u jednoj
    prijavljuje koji je to broj i koji partner, umesto da ceo dokument padne bez imena.
  - `resources/views/pdf/invoice.blade.php` je razložen na `pdf/partials/invoice-body`
    i `pdf/partials/invoice-styles`, koje dele pojedinačna faktura i objedinjena pošiljka —
    strane u pošiljci su iste kao kad se šalju jedna po jedna.
    `App\Actions\Invoices\RenderInvoicePdf::viewData()` sklapa podatke jedne štampane
    fakture (uključujući logo i IPS QR), pa ih više ne računa šablon. Logo se čita jednom
    po pravnom licu, koliko god faktura pošiljka imala.
  - Ekran `fakture/objedinjena-posiljka`: izbor grupe i perioda, spisak onoga što ulazi u
    dokument, spisak izostavljenog sa razlogom, preuzimanje i slanje na adresu grupe
    (`App\Mail\PartnerGroupBundleMail`, jedan prilog). Grupa bez adrese može samo da
    preuzme dokument. Iznad 40 faktura ekran upozorava da prilog može biti prevelik.
  - Ekrani `grupe-partnera`, `grupe-partnera/nova` i `grupe-partnera/{id}/izmena`,
    sa spiskom članova; partner se u grupu dodaje na svom formularu (polje „Grupa" u
    odeljku Kontakt). Neaktivna grupa ostaje na spisku dok drži tog partnera, da otvaranje
    formulara ne bi tiho poništilo članstvo.
  - Spisak partnera dobija kolonu i filter „Grupa".
  - Spisak faktura (`fakture`) dobija filter „Grupa" i dugme „Objedinjeni PDF" za izabranu
    grupu i period sa ekrana (`fakture/grupa/{partnerGroup}/pdf?god=…&mes=…`, ista akcija
    kao na ekranu pošiljke). **Sa spiska se preuzima samo period** — status i pretraga se
    ne prenose, jer bi otkucan pojam tiho izbacio fakturu iz pošiljke.
  - Rute grupa su registrovane pre `fakture/{invoice}`, jer statični segment dobija
    prednost samo ako je upisan ranije.
  - 29 novih testova: `tests/Feature/PartnerGroups/PartnerGroupManagementTest.php` i
    `tests/Feature/PartnerGroups/PartnerGroupBundleTest.php`.

- Ugovori: treći režim obračuna „Dva meseca unazad" (`App\Enums\BillingMode::ArrearsTwoMonths`,
  vrednost `unazad-2`). Obračun pokrenut u septembru pravi fakturu za jul, sa datumom
  prometa 31.07 — za aranžmane kod kojih obračun kasni ceo mesec. Postojeći režimi
  „Unazad" (septembar → avgust) i „Unapred" (septembar → septembar) ostaju nepromenjeni.
  Kolona `contracts.billing_mode` je `string(20)`, pa migracija nije potrebna.
  Novo stanje fabrike: `ContractFactory::billedTwoMonthsInArrears()`.

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
- **Logo pravnog lica.** Izdavalac može da ima logo, koji se štampa na svakom dokumentu.
  - Kolona `logo_path` u `companies` konačno je u upotrebi. Fajl stoji na privatnom disku
    (`storage/app/private/logos/{id}/`), nikada u `public/`.
  - `App\Support\SvgSanitizer` prepisuje SVG u bezbedan podskup pri unosu: uklanja
    `<script>`, `on*` atribute, `<foreignObject>`, animacije koje umeju da prepišu `href`,
    i sve reference van samog fajla. Fajl sa deklaracijom entiteta se odbija (XXE i
    „billion laughs"). Na disku završava već očišćen fajl, pa se ništa ne filtrira pri izdavanju.
  - `App\Actions\Companies\StoreCompanyLogo` prima SVG, PNG i JPG do 2 MB, proverava
    sadržaj a ne ekstenziju, briše prethodni fajl i radi unutar iste transakcije u kojoj
    se pravno lice kreira — odbijen logo ne ostavlja polufirmu za sobom.
  - `App\Http\Controllers\CompanyLogoController` (`/companies/{company}/logo`) izdaje fajl
    samo onome ko sme da vidi to pravno lice, uz `Content-Security-Policy: default-src 'none'`
    i `X-Content-Type-Options: nosniff`. Adresa nosi otisak fajla, pa zamenjen logo ne dolazi iz keša.
  - Formular pravnog lica dobija pregled, izbor fajla i dugme za uklanjanje; logo se može
    dodati i pri unosu nove firme. Dodaje ga samo admin, jer izmena pravnog lica i inače traži admina.
  - `resources/views/pdf/invoice.blade.php` štampa logo u zaglavlju, ugrađen kao `data:` URI —
    dompdf ne sme na mrežu. SVG izlazi kao vektor, oštar na svakom zumu i štampi.
  - Logo je ograničen sa `max-width: 180px; max-height: 42px`, nikad fiksnom visinom. SVG
    zadržava razmeru, pa je logo oblika bannera (npr. `viewBox="0 0 3000 100"`) sveden na
    42px visine ispadao **945 pt širok na strani od 595 pt** i bežao van papira.
  - `App\Support\SvgSymbolInliner` razrešava `<use>` reference u stvarnu geometriju pri unosu.
    php-svg-lib, koji dompdf koristi za SVG, skalira `<symbol>` prema korenskom viewport-u
    umesto prema `width`/`height` sa `<use>`: logo od 1480 pločica sa `viewBox="0 0 1.09 1.09"`
    dobijao je faktor `88.55 / 1.09 = 81.24` i **mastilo je prekrivalo 1082% širine strane**,
    dok je okvir slike i dalje merio urednih 42px. Posle razrešavanja isti logo se crta
    78 × 25 pt.
  - `InvoicePdfTest` više ne meri okvir slike nego **opseg samog mastila**, replayom
    crtačkih naredbi kroz stek transformacija — okvir je izgledao ispravno i kada je crtež
    bežao van papira. Pokriveno je pet oblika loga, uključujući onaj sastavljen od simbola.
  - 37 novih testova: `tests/Unit/SvgSanitizerTest.php`, `tests/Unit/SvgSymbolInlinerTest.php`,
    `tests/Feature/Companies/CompanyLogoTest.php` i sedam dopunjenih u
    `tests/Feature/Invoices/InvoicePdfTest.php`. Ukupno 194 testa prolaze.
- **M3 — ugovori i mesečno ponavljanje.** Mesec se svodi na pregled i potvrdu.
  - Tabele `contracts` i `contract_items`. Ugovor drži sve što faktura drži — partnera,
    valutu, rok plaćanja, račun, napomene i stavke — ali nikad broj ni iznose: oni pripadaju
    dokumentima koje pravi, svaki obračunat u trenutku nastanka. `invoices.contract_id`
    je konačno dobio strani ključ.
  - Enum `App\Enums\BillingMode` — **smer obračuna po ugovoru**: `unazad` fakturiše mesec
    koji je istekao (promet je poslednji dan tog meseca), `unapred` mesec koji počinje
    (promet je prvi dan). Održavanje ide unazad, zakup i pretplata unapred.
  - `App\Actions\Contracts\GenerateContractDrafts` pravi nacrte za zadati **dan**, ne za
    period: dva ugovora iste firme mogu istog 1. aprila fakturisati mart i april. Svaki
    ugovor sam računa svoj period. Ništa se ne izdaje — samo nacrti.
  - Važenje ugovora se proverava **prema periodu koji se fakturiše**, ne prema danu obračuna,
    pa ugovor istekao 31. marta i dalje dobija svoju martovsku fakturu, u aprilu.
  - Dupli obračun je nemoguć: ugovor pamti poslednji period koji je pretvorio u nacrt.
    Promašen dan se hvata kasnije u mesecu, a `generation_day` = 31 radi i u februaru.
  - Nacrt u stranoj valuti dobija **poslednji korišćeni kurs** za tu valutu umesto kursa 1,
    koji bi 1000 EUR nečujno pretvorio u 1000 RSD prometa.
  - Ekran `fakture/mesecno` — šta ugovori duguju na zadati dan, dugme za pravljenje nacrta,
    pa lista nacrta sa izborom i masovnim izdavanjem. `App\Actions\Invoices\IssueInvoices`
    izdaje jedan po jedan i prijavljuje koji nisu prošli: jedan neispravan dokument ne
    obara ostalih dvadeset.
  - Ekrani `ugovori`, `ugovori/novi` i `ugovori/{id}/izmena`, sa živim prikazom kada će
    i za koji period nastati sledeći nacrt. Ugovor koji već ima fakture se ne briše nego
    zatvara — dokumenti bi ostali bez objašnjenja.
  - Komanda `php artisan contracts:generate-drafts` (`--on`, `--company`, `--dry-run`),
    zakazana svakog dana u 06:00 u `routes/console.php`. Radi i bez prijavljenog korisnika.
  - **„Kopiraj u naredni mesec"** na fakturi (`App\Actions\Invoices\CopyInvoiceToNextPeriod`)
    — za klijente bez ugovora. Kopija zadržava stavke i uslove, a ostavlja sve što pripada
    izvornom dokumentu: broj, poziv na broj i datume. Datum prometa koji je bio poslednji
    u mesecu ostaje poslednji.
  - `App\Support\PeriodLabel` — nazivi meseci na jednom mestu, jer faktura, ugovor i
    mesečni pregled imenuju isti period.
  - 42 nova testa: `GenerateContractDraftsTest`, `MonthlyRunTest`, `ContractManagementTest`
    i `CopyInvoiceTest`.
- **NBS IPS QR kôd na PDF-u.** Izdata dinarska faktura nosi kôd koji se skenira u mobilnoj
  aplikaciji banke, pa se račun, iznos i poziv na broj ne prekucavaju.
  - `App\Support\IpsQrPayload` sastavlja tekst po NBS specifikaciji:
    `K:PR|V:01|C:1|R:{račun}|N:{primalac}|I:RSD{iznos}|P:{platilac}|SF:{šifra}|S:{svrha}|RO:{model+poziv}`.
    Dve stvari odlučuju da li banka prihvata kôd: iznos ide sa zarezom i bez separatora
    hiljada (`RSD105960,00`), a model se lepi uz poziv na broj bez razmaka (`9759-20260015`).
  - Vrednosti se čiste pre upisa — uspravna crta u nazivu firme bi rasekla polje na dva
    i pomerila sve iza njega — i seku na dužine koje standard dozvoljava (naziv 70, svrha 35).
  - Kôd se ne pojavljuje tamo gde nema smisla: na nacrtu (nema broja ni poziva na broj),
    na fakturi u stranoj valuti (IPS je domaći dinarski instant transfer) i kad račun
    izdavaoca nema 18 cifara.
  - Nova kolona `companies.payment_code` (podrazumevano `221` — bezgotovinski promet robe
    i usluga), sa validacijom „tri cifre, prva 1 ili 2". Šifra plaćanja je stvar izdavaoca,
    pa stoji na pravnom licu, uz polje u formularu.
  - `App\Actions\Invoices\RenderIpsQrCode` crta PNG (`endroid/qr-code`), sa nivoom ispravke
    grešaka *Medium* — kôd se skenira sa papira koji se savija i pečatira. PNG, a ne SVG,
    jer dompdf rasterske slike crta tačno onako kako su date.
  - Kôd se štampa uz rekapitulaciju, ivice 26 mm.
  - **Ispravke po prijavi NBS validatora** (kôd sa stvarnim podacima je padao na tri greške):
    - `RO` više ne nosi crticu. Poziv na broj se štampa kao `97 53-20260017`, gde crtica
      samo odvaja kontrolni broj za ljudsko oko; model 97 je u polju ne dozvoljava —
      „za ostale modele … ispravni su brojevi, slova i crtice". Sada odlazi `975320260017`.
      Kontrolni broj je bio ispravan i ostaje isti; crtica je bila jedini problem.
    - Ćirilica u nazivima se prepisuje u latinicu (`IpsQrPayload::latin()`). Validator
      odbija ceo kôd zbog jedne ćirilične reči, pa `Крагујевац` postaje `Kragujevac`.
      **Latinična dijakritika ostaje** — `Čačak` ostaje `Čačak`, menja se samo pismo.
    - Test `test_the_reference_passes_the_model_97_check` ponavlja proveru koju radi banka
      (referenca + „00", mod 97, oduzeto od 98), da kontrolni broj više ne zavisi od
      poverenja u dokumentaciju.
  - 21 test: `tests/Unit/IpsQrPayloadTest.php` i `tests/Feature/Invoices/IpsQrCodeTest.php`.
- **Stambena zajednica kao tip.** Od 2016. i Zakona o stanovanju stambena zajednica je
  pravno lice sa PIB-om i matičnim brojem, pa se uklapa u postojeća pravila bez izuzetka.
  - Novi slučaj u `App\Enums\PartnerType` — traži PIB i matični broj kao i svako pravno
    lice, a JMBG odbija. Formular i CSV uvoz su vođeni enumom, pa ga preuzimaju sami;
    uvoz prepoznaje i „stambena zajednica" sa razmakom.
  - Novi enum `App\Enums\CompanyType` i kolona `companies.type` (`pravno_lice` —
    podrazumevano, `preduzetnik`, `stambena_zajednica`). Postojeća pravna lica ostaju
    `pravno_lice`.
  - Stambena zajednica nema šifru delatnosti niti JBKJS, pa ta dva polja nestaju iz
    formulara kad se izabere taj tip — i briše se ono što je već otkucano u njima,
    umesto da se sačuva van vidokruga.
  - Lista pravnih lica označava sve što nije obično pravno lice, jer lista koja stambenu
    zajednicu zove firmom obmanjuje onoga ko je čita.
  - Nepoznata vrednost tipa više ne ruši formular. Polje je `wire:model.live`, pa je
    `CompanyType::from()` na proizvoljnoj vrednosti dizao `ValueError` i vraćao 500
    umesto poruke o grešci; sada se pada nazad na podrazumevani tip, a validacija
    odbija vrednost pri čuvanju.
  - 13 novih testova: `tests/Feature/Companies/CompanyTypeTest.php` i
    `tests/Feature/Partners/HousingCommunityPartnerTest.php`. Ukupno 270 testova prolazi.
- **Podrazumevani osnov oslobođenja od PDV-a po pravnom licu.** Firma van sistema PDV-a
  izdaje svaki dokument po istom članu zakona, pa osnov stoji na firmi, a ne na svakoj
  fakturi posebno.
  - Migracija `2026_09_04_103647_add_vat_exemption_reason_to_companies_table` dodaje
    `companies.vat_exemption_reason_id` (nullable, `nullOnDelete`), uz relaciju
    `Company::vatExemptionReason()`.
  - Formular pravnog lica dobija polje „Podrazumevani osnov oslobođenja od PDV-a" u
    odeljku „PDV i valuta", uz upozorenje kada firma nije u sistemu PDV-a a osnov nije
    izabran, i kada je šifarnik osnova prazan.
  - Osnov se spušta naniže: novi dokument u formularu fakture kreće od osnova firme, a
    `App\Actions\Invoices\SaveInvoiceItems` upisuje ga i na svaku stavku čija PDV
    kategorija traži osnov (`VatCategory::requiresExemptionReason()`) — tako ga
    EN 16931 traži, po kategoriji, a ne jednom po dokumentu. Osnov izabran na stavci
    ima prednost nad onim sa dokumenta.
  - `App\Actions\Contracts\GenerateContractDrafts` upisuje osnov firme na svaki nacrt.
  - Formular ugovora dobija izbor osnova po stavci („Sa pravnog lica" kada se ne bira
    posebno); vrednost se čuva u `contract_items.vat_exemption_reason_id` i prenosi na
    fakturu kroz `ContractItem::toInvoiceItem()`.
  - Novi `Invoice::exemptionReasons()` vraća sve osnove dokumenta, svaki po jednom —
    dokument sa mešovitim kategorijama štampa po jedan osnov za svaku.
  - 10 novih testova: `tests/Feature/Invoices/VatExemptionBasisTest.php`.
    Ukupno 285 testova prolazi.

- **`.cpanel.yml` — deploy na cPanel.** Git Version Control kopira klon
  repozitorijuma u `/home/skyvorte/fakture_app` (`rsync -a --delete`), pravi
  `storage/` i `bootstrap/cache` i pušta `composer install --no-dev
  --optimize-autoloader`. Deploy ne dira `.env`, `storage/` i `vendor/` — to
  pripada serveru. Document root domena treba da pokazuje na
  `/home/skyvorte/fakture_app/public`.
  - `/public/build` je izbačen iz `.gitignore`: Vite paketi se grade lokalno
    (`npm run build`) i commituju, jer se na sharedu ne računa na Node.
  - Migracije se i dalje puštaju ručno.

- **Naslovna strana na `/`.** Umesto Laravelove `welcome` strane stoji predstavna
  strana sa logotipom, opisom i dugmetom „Prijava" (prijavljenom korisniku se nudi
  „Kontrolna tabla"), plus potpis iz `config/global.php`
  (`resources/views/welcome.blade.php`). Testovi:
  `tests/Feature/WelcomeTest.php`.

- **Seederi prenose celu lokalnu bazu na produkciju.** Podaci koji su do sada
  postojali samo u lokalnoj bazi sada su u seederima, pa se produkcija puni sa
  `php artisan db:seed`:
  - `database/seeders/PartnerGroupSeeder.php` — 5 upravnika (grupa partnera),
    prepoznaju se po nazivu u okviru pravnog lica.
  - `database/seeders/ContractSeeder.php` — 79 ugovora sa stavkama, prepoznaju se
    po paru partner + naziv ugovora; stavke po rednom broju, pa ponovno
    pokretanje osvežava cenu umesto da napravi drugi ugovor istom partneru.
  - `database/seeders/PartnerSeeder.php` — dopunjen na 78 partnera i proširen
    poljima `email`, `contact_person` i pripadnošću grupi (`group` se razrešava u
    `partner_group_id`, spisak ne nosi ključeve iz baze).
  - `DatabaseSeeder` zove nove seedere redom `PartnerGroup` → `Partner` →
    `Contract`.
  - Sadržaj seedera je izvezen iz lokalne baze i upoređen sa njom red po red;
    4 nova testa u `tests/Feature/Seeders/DatabaseSeederTest.php`.

- **Zbir „Ukupno svi ugovori" na spisku ugovora.** Pored postojećeg
  „Mesečno na ovoj strani" stoji i mesečna vrednost celog spiska, ne samo tekuće
  strane (`resources/views/livewire/contracts/index.blade.php`). Oba zbira prate
  iste filtere — pretragu i prikaz neaktivnih — jer se sada računaju iz istog
  upita (`filteredContracts()`), pa se ne mogu razići.
  - 2 nova testa u `tests/Feature/Contracts/ContractManagementTest.php`.

### Izmenjeno

- Seedovanje baze više ne koristi izmišljene podatke. `DatabaseSeeder` sada zove
  `Database\Seeders\CompanySeeder` i `Database\Seeders\PartnerSeeder` umesto fabrika:
  - `CompanySeeder` upisuje stvarna pravna lica sa podacima iz registra, njihove
    tekuće račune i logotip (iz `database/seeders/assets/`, kopira se u
    `logos/{id}/` kao pri otpremanju). Prvo lice je „Ivan Zornić Pr Veb portali
    Digital Skyvortex" (PIB 114362087), preduzetnik van sistema PDV-a, sa osnovom
    `PDV-RS-33`. Osnov se seeduje ovde, a ne u `ReferenceDataSeeder`, jer ga traži
    konkretno pravno lice.
  - Brojač faktura Digital Skyvorteksa nastavlja numeraciju vođenu izvan aplikacije:
    za 2026. `last_number` je 638, pa je prva faktura 2026-0639. Brojači se upisuju
    kroz `firstOrCreate`, da ponovno pokretanje ne vrati brojač unazad preko već
    izdatih dokumenata.
  - `PartnerSeeder` upisuje 77 stvarnih kupaca Digital Skyvorteksa (75 stambenih
    zajednica, jedno pravno lice i jedan preduzetnik).
  - Seederi se poklapaju po PIB-u, pa ponovno pokretanje osvežava red umesto da
    napravi drugi. Korisnici se upisuju kroz `updateOrCreate`.
  - Fakture i ugovori se više ne seeduju — baza kreće prazna, dokumenti se unose
    kroz aplikaciju.
  - Novi testovi: `tests/Feature/Seeders/DatabaseSeederTest.php`.
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

- `docs/plan-razvoja.md`: uvedene **grupe partnera i objedinjena pošiljka**. Nova tabela
  `partner_groups` (naziv, `email`, `is_active`) i `partners.partner_group_id` — partner je
  u najviše jednoj grupi, a grupa prima jedan PDF sa svim izdatim fakturama svojih partnera
  za izabrani period, na jednu adresu. Dokument počinje naslovnom rekapitulacijom sa
  ukupnim zbirom, pa idu fakture jedna za drugom; grupa ne dira fakturisanje, faktura i
  dalje glasi na pojedinačnog partnera sa svojim pozivom na broj i IPS QR kodom.
  Dodata faza **M3a** (posle M3), tehnička odluka da se objedinjeni PDF iscrtava kao jedan
  Blade pogled sa prelomom strane — bez spajanja gotovih PDF-ova i bez nove zavisnosti —
  sedam novih donetih odluka i rizik 8 (veličina priloga kod velikih grupa).
  Objedinjeni PDF se pokreće i sa spiska faktura (`invoices.index`): filter „Grupa" pored
  filtera statusa i dugme koje pravi dokument za izabranu grupu i period sa ekrana
  (`fakture/grupa/{partner_group}/pdf`). Sa spiska se preuzima samo period — status i
  pretraga se ne prenose u dokument.

### Uklonjeno

- **Registracija naloga.** Aplikacija je interna i naloge otvara administrator na
  `korisnici/novi`, pa je samostalna registracija uklonjena: ruta `register` iz
  `routes/auth.php`, ekran `resources/views/livewire/auth/register.blade.php` i veza
  „Sign up" sa strane za prijavu. `tests/Feature/Auth/RegistrationTest.php` sada
  proverava da registracije nema (ruta ne postoji, `/register` vraća 404, prijava ne
  nudi otvaranje naloga).

### Ispravljeno

- **Aplikacija je merila vreme po serveru, a ne po Srbiji.** `config/app.php` je držao
  `'timezone' => 'UTC'` bez `env()` ključa, pa je na sharedu (koji radi u UTC-u) svaki
  `now()` kasnio sat-dva. Formular fakture iz toga izvlači `issue_date`, `supply_date`,
  `due_date` i obračunski period: dokument otvoren posle ponoći dobijao je jučerašnji
  datum, a prvog u mesecu i prethodni period. Sada je `env('APP_TIMEZONE',
  'Europe/Belgrade')`, uz `APP_TIMEZONE=Europe/Belgrade` u `.env.example`.
  Podrazumevani jezik je iz istog razloga `sr` umesto `en` (a `faker_locale` `sr_RS`) —
  `.env` na serveru se podešava ručno i ključ ume da izostane.

- **Naslov perioda na spisku faktura ispisivao je engleski naziv meseca.**
  `resources/views/livewire/invoices/index.blade.php` je jedini koristio Carbon-ov
  `translatedFormat('F Y')`, koji zavisi od lokala aplikacije, pa je uz nepodešen
  `APP_LOCALE` pisalo „September 2026." dok je ostatak ekrana pisao „septembar".
  Sada i on ide kroz `App\Support\PeriodLabel`, jedino mesto sa nazivima meseci.

- 3 nova testa: `tests/Feature/AppTimeAndLocaleTest.php`.

- **Deploy na cPanel je padao na nepostojećem composer-u.** `.cpanel.yml` je pozivao
  `/opt/cpanel/composer/bin/composer`, kojeg na nalogu nema — composer je na
  `/usr/local/bin/composer`. Poslednji task je vraćao 127 i rušio ceo deploy, pa na server
  nikada nije stizao `composer install`. `PHPBIN` je prebačen na `/usr/local/bin/php`
  (podrazumevani PHP naloga, 8.4), pošto domen nema `AddHandler` override u
  `public/.htaccess` i time prati MultiPHP Manager. Dodato je i brisanje
  `bootstrap/cache/{packages,services,config,compiled}.php` pre instalacije, da
  `package:discover` iz `post-autoload-dump` skripte ne čita zatečeni keš.

- **Sajt je vraćao 404 iako je document root bio tačan.** `/bin/mkdir -p $DEPLOYPATH` u
  `.cpanel.yml` pravi radni direktorijum pod umask-om deploy procesa, što daje dozvole
  700; LiteSpeed onda ne može da uđe u `fakture_app` i odgovara sa 404 pre nego što
  uopšte stigne do `public/index.php`. Dodat je `chmod 711 $DEPLOYPATH` odmah posle
  `mkdir`-a.

- **Na fakturama firme van sistema PDV-a nije pisao pravni osnov.** PDF je ispisivao samo
  „Izdavalac nije u sistemu PDV-a.", a dopunu sa članom zakona tek `@if` na
  `invoice->vatExemptionReason` — koji nikad nije bio popunjen, jer se osnov birao ručno
  na svakoj fakturi i niko ga nije birao. Takav dokument bi na SEF-u pao na
  BT-120 / BT-121. Sada osnov stiže sa pravnog lica, `resources/views/pdf/invoice.blade.php`
  ispisuje svaki osnov dokumenta, a `App\Actions\Invoices\IssueInvoice` odbija izdavanje
  dokumenta koji ima stavku bez PDV-a i bez osnova.

- **Dugme „pauza" na spisku ugovora brisalo je red sa ekrana bez ijedne reči** — spisak
  podrazumevano skriva neaktivne ugovore, pa je pauziranje izgledalo kao da je ugovor
  obrisan. `toggleActive()` u `resources/views/livewire/contracts/index.blade.php` sada
  ispisuje poruku o tome šta se desilo i, kad pauzira, uključuje filter „Prikaži i
  neaktivne" da bi red ostao vidljiv sa oznakom „neaktivan".

- **Nijedan upload nije radio na Laragonu** — ni logo, ni CSV uvoz partnera. Apache-ov PHP
  nije imao `upload_tmp_dir`, pa je fajl završavao u `C:\Windows\Temp`; upload bi uspeo
  (`error 0`, `file_exists: true`), ali `realpath()` nad tim fajlom vraća `false` jer proces
  nema prava nad tim direktorijumom. Laravel u `putFileAs()` radi
  `fopen($file->getRealPath(), 'r')`, pa je svaki upload pucao sa
  `ValueError: Path must not be empty`, a Livewire je to prikazivao samo kao
  „The logo failed to upload." Rešenje je `upload_tmp_dir = "C:/laragon/tmp"` u
  `php.ini` — nije u repozitorijumu, pa je zapisano u sekciji `=== local environment rules ===`
  u `CLAUDE.md`.
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
- **Onemogućen prazan `<option>` u padajućim listama.** Placeholder je bio `disabled`,
  a pregledač ne može da drži onemogućenu opciju kao izabranu, pa je prikazivao prvu
  stvarnu. Korisnik je video partnera u polju, ali pošto ništa nije menjao, `change` se
  nije okidao i izbor nikada nije stizao do servera — otud „The partner id field is
  required" uz naizgled izabranog partnera. Uklonjen je `disabled`, a uz to i Fluxov
  `placeholder` prop tamo gde ekran već iscrtava sopstvenu praznu opciju, jer su nastajale
  dve prazne opcije u istom selectu.
- **Formular fakture ispisivao je ceo red iz tabele `currencies` umesto oznake valute.**
  U `resources/views/livewire/invoices/form.blade.php` petlja `@foreach ($currencies as $currency)`
  koristila je isto ime kao `public string $currency`, a Blade promenljiva iz petlje živi
  i posle nje — pa je ostatak šablona video poslednju iscrtanu valutu (`GBP`, `id: 5`)
  serijalizovanu u JSON. Videlo se na „Vrednost stavke", u „Rekapitulaciji" i kod
  „Za uplatu". Uz to je uslov `$currency !== 'RSD'` uvek bio tačan, pa se polje „Kurs"
  prikazivalo i za dinarske fakture. Petlja je preimenovana u `$currencyOption`.
  - `InvoiceFormCurrencyDisplayTest` proverava da su iznosi označeni oznakom valute
    i da se „Kurs" pojavljuje samo za stranu valutu.

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
