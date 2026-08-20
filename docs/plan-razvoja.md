# Plan razvoja — SkyVortex Fakture

Aplikacija za izdavanje faktura u ime više pravnih lica i praćenje poslovanja,
sa kasnijim povezivanjem na Sistem e-faktura (SEF).

Dokument je živ — menja se kako se donose odluke.

---

## 1. Opseg

### Faza 1 — fakturisanje i praćenje poslovanja

- Više pravnih lica izdavalaca u istoj instalaciji.
- Primaoci: pravna lica, preduzetnici, fizička lica i strana lica.
- Fakturisanje organizovano po mesecima.
- Ponavljanje mesečnog fakturisanja preko **ugovora / šablona** koji svakog meseca
  generišu nacrte faktura, koji se pre izdavanja mogu izmeniti.
- Dve uloge: **admin** (sva pravna lica) i **knjigovođa** (samo dodeljena pravna lica).
- **Mešovit PDV režim** — neka pravna lica su u sistemu PDV-a, neka nisu.
- **Više valuta** sa evidencijom kursa i protivvrednosti u RSD.
- Praćenje naplate, uvoz bankarskih izvoda u XML formatu i ručni unos papirnih izvoda,
  sa automatskim uparivanjem uplata; ulazne fakture i troškovi; analitika.

### Faza 2 — SEF

- Slanje izlaznih faktura na Sistem e-faktura, praćenje statusa, storniranje.

### Van opsega (za sada)

- Obračun zarada, osnovna sredstva, robno-materijalno knjigovodstvo.
- Fiskalizacija (maloprodaja).
- Mobilna aplikacija.

---

## 2. Model podataka

Sve tabele koje pripadaju pravnom licu nose `company_id` i kroz njega se primenjuje
kontrola pristupa.

### Osnovni entiteti

| Tabela | Uloga | Ključna polja |
| --- | --- | --- |
| `companies` | pravno lice izdavalac | naziv, PIB, matični broj, adresa, šifra delatnosti, `in_vat_system`, JBKJS, logo, podrazumevana valuta |
| `bank_accounts` | računi izdavaoca | banka, broj računa, IBAN, SWIFT, valuta, `is_primary` |
| `partners` | primaoci faktura | `type` (pravno lice / preduzetnik / fizičko lice / strano lice), naziv ili ime i prezime, PIB, matični broj, JMBG, VAT ID, adresa, država, `in_vat_system`, JBKJS, podrazumevani rok plaćanja |
| `users` | korisnici | `role` (admin / knjigovođa) |
| `company_user` | dodela pravnih lica knjigovođi | `company_id`, `user_id` |

Partneri se vode po pravnom licu, ne globalno. Isti klijent kod dva izdavaoca znači dva
zapisa — to je namerno, jer čuva kontrolu pristupa jednostavnom. Objedinjavanje po PIB-u
može doći kasnije ako se pokaže kao problem.

**Izdavalac i primalac ne mogu biti isto pravno lice na istoj fakturi.** Provera se radi
po PIB-u pri izboru partnera i ponovo pri izdavanju — firma ne može da fakturiše samoj sebi.

Pravilo je namerno usko: dva pravna lica iz sistema **smeju** međusobno da fakturišu.
Firma B se tada, kod firme A, vodi kao običan partner.

> Posledica: isti PIB tada postoji u `companies` i u `partners`, kao dva odvojena zapisa
> koji se održavaju odvojeno. Promena adrese firme B ne stiže sama do partnerskog zapisa
> kod firme A. Ako to počne da smeta, rešenje je predlaganje podataka iz `companies`
> pri unosu partnera — ne i spajanje zapisa, jer bi to probilo kontrolu pristupa.

### Fakturisanje

| Tabela | Uloga | Ključna polja |
| --- | --- | --- |
| `invoices` | izlazni dokument | `company_id`, `partner_id`, `type` (faktura / avansna / knjižno odobrenje / knjižno zaduženje / predračun), `number`, `period_year`, `period_month`, `issue_date`, `supply_date`, `due_date`, `currency`, `exchange_rate`, iznosi u valuti i u RSD, `status`, `payment_reference`, `vat_exemption_code`, `contract_id`, `source_invoice_id` |
| `invoice_items` | stavke | naziv, opis, `unit_code`, količina, jedinična cena, rabat, `vat_rate`, `vat_category`, iznosi, redosled |
| `invoice_number_sequences` | brojači | `company_id`, `type`, `year`, `last_number` |
| `contracts` | ugovor / šablon | `company_id`, `partner_id`, naziv, učestalost, dan generisanja, `starts_on`, `ends_on`, valuta, rok plaćanja, `is_active`, `last_generated_period` |
| `contract_items` | stavke šablona | ista struktura kao `invoice_items` |

**Datum prometa (`supply_date`) je odvojen od datuma izdavanja** — poreski period se
određuje po datumu prometa, a ne po datumu izdavanja fakture.

**Statusi fakture:** nacrt → izdata → (delimično plaćena) → plaćena, uz stornirana kao
zaseban ishod. Nacrt se slobodno menja i briše; izdata se ne menja.

**Numeracija.** Format broja je `2026-0001` — godina, crtica, redni broj sa četiri cifre
i vodećim nulama. Brojač je po pravnom licu, tipu dokumenta i godini.

Pošto u tekućoj godini već postoje fakture izdate van aplikacije, pri unosu pravnog lica
se zadaje **poslednji iskorišćen broj**, pa aplikacija nastavlja od narednog. Dodela broja
ide u transakciji sa `lockForUpdate`, da dve istovremeno izdate fakture ne dobiju isti broj.

Ostali tipovi dokumenata dobijaju **prefiks**, da se ne sudaraju sa fakturama. Format se
čuva kao šablon po tipu dokumenta i podesiv je po pravnom licu, bez izmene koda:

| Tip dokumenta | Šablon | Primer |
| --- | --- | --- |
| faktura | `{godina}-{broj}` | `2026-0001` |
| avansna faktura | `AV-{godina}-{broj}` | `AV-2026-0001` |
| knjižno odobrenje | `KO-{godina}-{broj}` | `KO-2026-0001` |
| knjižno zaduženje | `KZ-{godina}-{broj}` | `KZ-2026-0001` |
| predračun | `PR-{godina}-{broj}` | `PR-2026-0001` |

`{broj}` je redni broj sa četiri cifre i vodećim nulama. Svaki tip ima sopstveni brojač,
pa `2026-0001` i `KO-2026-0001` postoje uporedo i ne smetaju jedan drugom.

### Naplata i banka

| Tabela | Uloga |
| --- | --- |
| `payments` | uplata po fakturi: datum, iznos, valuta, kurs, način plaćanja, veza na stavku izvoda |
| `bank_statements` | zaglavlje izvoda: račun, broj, datum, početno i krajnje stanje, `source` (uvoz XML / ručni unos) |
| `bank_statement_lines` | stavke izvoda: datum, iznos, smer, platilac, svrha, model i poziv na broj, status uparivanja |

### Troškovi

| Tabela | Uloga |
| --- | --- |
| `purchase_invoices` | ulazna faktura: dobavljač, broj, datum, datum prometa, valuta, osnovica, PDV, status plaćanja, prilog |
| `expense_categories` | kategorije troškova |

### Šifarnici

Valute, jedinice mere (UN/ECE Rec 20), PDV stope, PDV kategorije i osnovi oslobođenja
od PDV-a — kao seed podaci, jer ih traži i faza 2.

---

## 3. Tehničke odluke

| Oblast | Odluka | Obrazloženje |
| --- | --- | --- |
| UI | Livewire 4 + Volt + Flux UI | već instalirano starter kit-om |
| Autorizacija | `role` enum + Laravel Policies + global scope po `company_id` | dve uloge ne opravdavaju `spatie/laravel-permission` |
| Izbor pravnog lica | prekidač u zaglavlju, aktivno pravno lice u sesiji | knjigovođa radi sa više firmi naizmenično |
| Novac | `decimal(15,4)` za jedinične cene, `decimal(15,2)` za iznose | izbegava se greška `float` aritmetike |
| PDV obračun | po stavci, pa zbir po PDV kategoriji | tako traži i UBL u fazi 2 |
| PDF | `mpdf` ili `dompdf` sa DejaVu fontom | pouzdana latinica i ćirilica, bez Chromium-a na serveru |
| Poziv na broj | model 97, kontrolni broj po ISO 7064 MOD 97-10 | preduslov za automatsko uparivanje izvoda |
| Kurs | ručni unos na fakturi, sa predlogom poslednjeg unetog kursa za tu valutu | bez zavisnosti od spoljnog servisa i bez registracije kod NBS-a |
| Numeracija | `2026-0001`, brojač po pravnom licu, tipu dokumenta i godini, početna vrednost se zadaje pri unosu firme | nastavlja se na fakture izdate van aplikacije |
| Storniranje | dozvoljeno svim ulogama, bez odobrenja | knjigovođa radi samostalno; odgovornost nosi revizioni trag |
| Revizioni trag | izmene nad fakturama i plaćanjima, uz storniranje | izdati dokument mora imati istoriju, tim pre što storno niko ne odobrava |
| Testovi | PHPUnit, obavezno za scoping, numeraciju i PDV obračun | to su mesta gde greška najviše košta |

---

## 4. Faze isporuke

### M0 — Temelji pristupa

Uloge, `company_user` pivot, Policies, global scope, prekidač aktivnog pravnog lica,
CRUD pravnih lica i bankovnih računa, revizioni trag.

Pri unosu pravnog lica zadaje se i **poslednji iskorišćen broj fakture u tekućoj godini**,
da bi se numeracija nastavila na dokumente izdate pre uvođenja aplikacije.

*Gotovo kada:* knjigovođa vidi isključivo dodeljena pravna lica, i to je pokriveno testovima.

### M1 — Šifarnici i partneri

Partneri sva četiri tipa, šifarnici (valute, jedinice mere, PDV stope i osnovi oslobođenja),
uvoz partnera iz CSV-a.

*Gotovo kada:* partner se može uneti i pretražiti, a šifarnici su popunjeni seed-om.

### M2 — Fakture

Jezgro sistema. Faktura sa stavkama, obračun PDV-a za oba režima, valuta i kurs,
transakciono bezbedna numeracija, poziv na broj, tok nacrt → izdata → stornirana,
mesečni pregled, PDF i slanje mejlom.

*Gotovo kada:* mesec se može odfakturisati od početka do kraja, sa ispravnim PDF-om.

### M3 — Ugovori i mesečno ponavljanje

Ugovor sa stavkama i periodom važenja, zakazani posao koji generiše nacrte za naredni mesec,
pregled onoga što će biti fakturisano, masovno izdavanje, izmena pojedinačnog nacrta
pre izdavanja.

*Gotovo kada:* mesečno fakturisanje se svodi na pregled i potvrdu.

> Opciono, mali dodatak: akcija „kopiraj prošli mesec" za fakture koje nisu pokrivene
> ugovorom. Odgovara tvom prvobitnom opisu i jeftina je kad ugovori već postoje.

### M4 — Naplata i izvodi

Plaćanja i delimična plaćanja, statusi dospeća, opomene i IOS.

Izvod ulazi u sistem na **dva načina**, koja se dalje ponašaju isto:

1. **Uvoz XML izvoda** — svi izvodi koji stižu elektronski su u XML formatu.
2. **Ručni unos** — za izvode koji stižu u papiru. Unosi se zaglavlje izvoda i stavke,
   posle čega ide kroz isto uparivanje kao i uvezeni.

Uparivanje je zajedničko za oba: automatsko po pozivu na broj i iznosu, uz ručno
uparivanje i mogućnost da se stavka označi kao nebitna.

*Gotovo kada:* uvezen izvod sam zatvara fakture koje ima čime da upari, a papirni se
unese ručno bez zaobilaženja tog istog uparivanja.

### M5 — Ulazne fakture i troškovi

Ulazne fakture, kategorije troškova, prilozi, status plaćanja.

### M6 — Analitika

Kontrolna tabla: promet po mesecu i po klijentu, naplaćeno naspram nenaplaćenog,
starost potraživanja, bruto rezultat po pravnom licu, izvoz u Excel.

### M7–M9 — Faza 2, SEF

- **M7** — mapiranje fakture u UBL 2.1 i validacija, bez slanja.
- **M8** — slanje na demo okruženje SEF-a, praćenje statusa, storno, obrada grešaka.
- **M9** — produkcija, javni sektor (CRF), elektronsko evidentiranje PDV-a.

---

## 5. Rizici i otvorena pitanja

| # | Rizik | Kako se smanjuje |
| --- | --- | --- |
| 1 | **Šema XML izvoda.** Svi elektronski izvodi su u XML formatu, ali šema nije ista kod svih banaka. | Potreban uzorak stvarnog XML izvoda svake banke pre procene M4. Parser po banci iza zajedničkog interfejsa, tako da dodavanje nove banke ne dira uparivanje. |
| 2 | **SEF se menja**, i API i propisi. Detalje treba proveriti u zvaničnoj dokumentaciji neposredno pre M7, ne oslanjati se na pretpostavke iz ovog dokumenta. | Faza 2 se planira tek kada faza 1 radi, pa se mapiranje pravi po tada važećoj specifikaciji. |
| 3 | **JMBG fizičkih lica** je lični podatak. | Enkripcija u bazi, prikaz samo kada je nužan, evidencija pristupa. |
| 4 | **Nepromenljivost izdatih dokumenata.** | Izdata faktura se ne menja — ispravlja se knjižnim odobrenjem ili storniranjem. |
| 5 | **Ručno unet kurs je greška koja se ne primeti odmah.** | Predlog poslednjeg kursa za tu valutu, prikaz protivvrednosti u RSD pre izdavanja, upozorenje na neuobičajeno odstupanje. |
| 6 | **Storno bez odobrenja.** Svako ko vidi fakturu može da je stornira. | Revizioni trag beleži ko je i kada stornirao; storno se ne briše. |
| 7 | Puna analitika sa izvodima je **najveći pojedinačni modul** u fazi 1. | M4 i M6 su namerno posle M2 i M3, da fakturisanje ranije uđe u upotrebu. |

### Donete odluke

| Pitanje | Odluka |
| --- | --- |
| Format broja fakture | `2026-0001`, po pravnom licu / tipu dokumenta / godini. Početna vrednost se zadaje pri unosu firme, jer se nastavlja na fakture izdate van aplikacije. |
| Prefiks po tipu dokumenta | Da. Faktura ostaje `2026-0001`, ostali tipovi dobijaju prefiks (`AV-`, `KO-`, `KZ-`, `PR-`), svaki sa sopstvenim brojačem. |
| Ko sme da stornira | Sve uloge, bez odobrenja admina. |
| Kurs strane valute | Ručni unos, bez spoljnog servisa. |
| Izdavalac kao partner | Zabranjeno samo na istoj fakturi — firma ne fakturiše samoj sebi. Dve firme iz sistema smeju međusobno da fakturišu. |

### Preostalo otvoreno

Ništa što blokira M0–M3.

1. Uzorci XML izvoda po banci — potrebni pred procenu M4, ne ranije.

---

## 6. Redosled rada

Preporučeni redosled je M0 → M1 → M2 → M3, pa tek onda M4 → M5 → M6.

Razlog je što M2 i M3 zajedno već pokrivaju svakodnevni posao — mesečno fakturisanje —
pa aplikacija može da uđe u upotrebu dok se praćenje naplate još razvija. Obrnut redosled
znači duže čekanje na prvu upotrebljivu verziju, bez dobitka.
