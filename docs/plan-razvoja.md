# Plan razvoja — SkyVortex Fakture

Aplikacija za izdavanje faktura u ime više pravnih lica i praćenje poslovanja,
sa kasnijim povezivanjem na Sistem e-faktura (SEF).

Dokument je živ — menja se kako se donose odluke.

---

## 1. Opseg

### Faza 1 — fakturisanje i praćenje poslovanja

- Više pravnih lica izdavalaca u istoj instalaciji.
- Primaoci: pravna lica, preduzetnici, stambene zajednice, fizička lica i strana lica.
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
| `companies` | izdavalac dokumenata | `type` (pravno lice / preduzetnik / stambena zajednica), naziv, PIB, matični broj, adresa, šifra delatnosti, `in_vat_system`, JBKJS, logo, podrazumevana valuta, `payment_code` |
| `bank_accounts` | računi izdavaoca | banka, broj računa, IBAN, SWIFT, valuta, `is_primary` |
| `partners` | primaoci faktura | `type` (pravno lice / preduzetnik / stambena zajednica / fizičko lice / strano lice), naziv ili ime i prezime, PIB, matični broj, JMBG, VAT ID, adresa, država, `in_vat_system`, JBKJS, podrazumevani rok plaćanja, `partner_group_id` |
| `partner_groups` | grupa partnera koja prima jednu objedinjenu pošiljku | `company_id`, naziv, `email`, `is_active`, napomene |
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

### Grupe partnera

Neki partneri se plaćaju sa jednog mesta: više stambenih zajednica pod istim upravnikom,
više firmi istog vlasnika, ogranci koji imaju zajedničko računovodstvo. Takvi partneri se
svrstavaju u **grupu**, a grupa nosi jednu e-mail adresu na koju se šalje **jedan PDF sa
svim izdatim fakturama grupe za izabrani period**.

Grupa je vlastiti entitet (`partner_groups`), a ne oznaka na partneru, jer mora da nosi
adresu za slanje i svoja podešavanja. Grupa pripada pravnom licu izdavaocu i vidi se kroz
isti `company_id` scoping kao i partneri.

Članstvo je opciono i najviše jedno: partner je ili u tačno jednoj grupi
(`partners.partner_group_id`) ili ni u jednoj, i tada se fakture šalju njemu kao i do sada.
Grupa **ne menja fakturisanje** — fakture i dalje glase na pojedinačnog partnera, sa
sopstvenim brojem, pozivom na broj i IPS QR kodom. Grupa je isključivo način isporuke.

Objedinjena pošiljka obuhvata fakture partnera iz grupe za izabranu godinu i mesec, sa
statusom **izdata** (i dalje kroz delimično plaćena i plaćena). Nacrti i stornirane fakture
se izostavljaju — nacrt nije dokument, a storno je već poslat sam za sebe.

Dokument počinje **naslovnom rekapitulacijom** — naziv grupe, period, spisak faktura
(broj, partner, iznos) i ukupan zbir — posle koje ide svaka faktura na svojoj strani, u
istom izgledu kao kad se šalje pojedinačno.

### Fakturisanje

| Tabela | Uloga | Ključna polja |
| --- | --- | --- |
| `invoices` | izlazni dokument | `company_id`, `partner_id`, `type` (faktura / avansna / knjižno odobrenje / knjižno zaduženje / predračun), `number`, `period_year`, `period_month`, `issue_date`, `supply_date`, `due_date`, `currency`, `exchange_rate`, iznosi u valuti i u RSD, `status`, `payment_reference`, `vat_exemption_code`, `contract_id`, `source_invoice_id` |
| `invoice_items` | stavke | naziv, opis, `unit_code`, količina, jedinična cena, rabat, `vat_rate`, `vat_category`, iznosi, redosled |
| `invoice_number_sequences` | brojači | `company_id`, `type`, `year`, `last_number` |
| `contracts` | ugovor / šablon | `company_id`, `partner_id`, naziv, `reference`, `frequency`, `billing_mode` (unazad / unapred), `generation_day`, `starts_on`, `ends_on`, valuta, rok plaćanja, `bank_account_id`, napomene, `is_active`, `last_generated_year` + `last_generated_month` |
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
| Objedinjeni PDF grupe | jedan Blade pogled koji redom iscrtava rekapitulaciju i sve fakture, sa `page-break-before` između njih | dompdf ne ume da spaja gotove PDF-ove; spajanje bi tražilo novu zavisnost (`setasign/fpdi`), a ovako zaglavlje, podnožje i izgled ostaju isti kao kod pojedinačne fakture |
| Poziv na broj | model 97, kontrolni broj po ISO 7064 MOD 97-10 | preduslov za automatsko uparivanje izvoda |
| NBS IPS QR | `endroid/qr-code`, PNG ugrađen u PDF, šifra plaćanja po pravnom licu | plaćanje skeniranjem bez prekucavanja; PNG jer dompdf rasterske slike crta tačno onako kako su date |
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

### M3a — Grupe partnera i objedinjena pošiljka

Radi se **posle M3**, kada mesečno fakturisanje već stoji, jer objedinjena pošiljka ima
smisla tek nad izdatim fakturama celog meseca.

- Tabela `partner_groups` i `partners.partner_group_id`, sa `company_id` scoping-om kao
  i ostali podaci pravnog lica; politika `PartnerGroupPolicy`.
- CRUD grupa i izbor grupe na formularu partnera; na spisku partnera vidi se pripadnost.
- Akcija koja za grupu i period skuplja izdate fakture njenih partnera i iscrtava jedan
  PDF: naslovna rekapitulacija sa ukupnim zbirom, pa fakture jedna za drugom.
- Preuzimanje tog PDF-a i slanje na adresu grupe, jednim mejlom sa jednim prilogom.
- Ekran „objedinjena pošiljka": izbor grupe i perioda, pregled šta ulazi u dokument
  (i koje fakture su izostavljene, sa razlogom), pa preuzimanje ili slanje.
- **Na spisku faktura** (`invoices.index`) dolazi filter „Grupa" pored filtera statusa i
  dugme koje pravi objedinjeni PDF za izabranu grupu i period koji je na ekranu
  (`fakture/grupa/{partner_group}/pdf?god=…&mes=…`, ista akcija kao na ekranu pošiljke).
  Dugme se vidi samo kad je grupa izabrana i kad u tom periodu ima šta da uđe u dokument.

Sadržaj objedinjenog PDF-a **ne zavisi od filtera na ekranu**. Period se preuzima sa
spiska (godina i mesec, ili cela godina kad je mesec isključen), ali status i pretraga se
ne prenose — dokument uvek nosi izdate fakture grupe, po pravilu iz odeljka „Grupe
partnera". Inače bi otkucan pojam u pretrazi tiho izbacio fakturu iz pošiljke.

*Gotovo kada:* upravnik koji drži deset stambenih zajednica dobija jedan mejl sa jednim
PDF-om u kojem su sve fakture tog meseca, a svaka i dalje glasi na svoju zajednicu.

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
| 8 | **Objedinjeni PDF raste sa brojem članova grupe** — grupa od pedeset zajednica daje prilog koji poštanski server može odbiti, a jedna loša faktura u nizu ruši ceo dokument. | Prikaz broja faktura i procenjene veličine pre slanja; gornja granica po pošiljci, sa deljenjem na više mejlova kad se pređe. Fakture se iscrtavaju u jednom prolazu, pa greška u jednoj mora da prijavi koja je to, a ne da padne bez imena. |

### Donete odluke

| Pitanje | Odluka |
| --- | --- |
| Format broja fakture | `2026-0001`, po pravnom licu / tipu dokumenta / godini. Početna vrednost se zadaje pri unosu firme, jer se nastavlja na fakture izdate van aplikacije. |
| Prefiks po tipu dokumenta | Da. Faktura ostaje `2026-0001`, ostali tipovi dobijaju prefiks (`AV-`, `KO-`, `KZ-`, `PR-`), svaki sa sopstvenim brojačem. |
| Ko sme da stornira | Sve uloge, bez odobrenja admina. |
| Kurs strane valute | Ručni unos, bez spoljnog servisa. |
| Izdavalac kao partner | Zabranjeno samo na istoj fakturi — firma ne fakturiše samoj sebi. Dve firme iz sistema smeju međusobno da fakturišu. |
| Smer obračuna kod ugovora | Bira se **po ugovoru**: `unazad` fakturiše mesec koji je istekao (promet je poslednji dan tog meseca), `unapred` mesec koji počinje (promet je prvi dan). Održavanje ide unazad, zakup i pretplata unapred — jedno pravilo za sve klijente ne bi valjalo. |
| Period se računa iz dana obračuna | Ugovor sa `generation_day` = 1 i smerom `unazad` fakturiše, prvog u mesecu, mesec pre njega. Nacrt za april se ne može napraviti 25. marta; ko hoće ranije slanje, menja datum izdavanja na samom nacrtu. |
| Važenje ugovora se proverava prema periodu | Ne prema danu kad se nacrt pravi. Ugovor koji je istekao 31. marta i dalje duguje martovsku fakturu, a ona nastaje u aprilu — posle isteka ugovora. |
| Grupa partnera je zaseban entitet | Tabela `partner_groups`, a ne tekstualna oznaka na partneru ni roditelj-dete veza među partnerima. Grupa mora da nosi adresu za slanje i svoja podešavanja, a primalac objedinjene pošiljke nije primalac fakture. |
| Šta ulazi u objedinjeni PDF | Fakture partnera iz grupe za izabranu godinu i mesec, sa statusom izdata, delimično plaćena ili plaćena. Nacrti i stornirane se izostavljaju. Filter „samo neposlate" nije uzet, jer bi tražio `sent_at` na `invoices`, a ista pošiljka se u praksi šalje ponovo. |
| Izgled objedinjenog dokumenta | Naslovna rekapitulacija (grupa, period, spisak faktura sa iznosima, ukupan zbir), pa svaka faktura na svojoj strani u postojećem izgledu. Primalac vidi ukupan iznos bez sabiranja priloga. |
| Grupa ne dira fakturisanje | Faktura i dalje glasi na pojedinačnog partnera, sa svojim brojem, pozivom na broj i IPS QR kodom. Grupa je način isporuke, ne obračunska celina — objedinjena naplata bi tražila zbirni poziv na broj, što nije traženo. |
| Kada se radi | Posle M3, kao M3a. Zavisi od izdatih faktura celog meseca, koje daje M3. |
| Gde se pokreće objedinjeni PDF | Sa dva mesta, ali kroz istu akciju: sa ekrana „objedinjena pošiljka" i sa spiska faktura, gde postoje filter po grupi i dugme za PDF izabrane grupe u prikazanom periodu. Spisak faktura je mesto na kojem se ionako radi mesečni posao. |
| Filteri spiska ne ulaze u dokument | Sa spiska se preuzima samo period. Status i pretraga se ne prenose — dokument uvek nosi izdate fakture grupe, da otkucan pojam u pretrazi ne bi tiho izbacio fakturu iz pošiljke. |
| Učestalost ugovora | Za sada samo mesečno. `invoices` nosi `period_month`, pa bi kvartalni dokument morao da izabere jedan mesec kao svoj period, čime bi i mesečni pregled i štampani period lagali. Odluka se donosi kad se pojavi kvartalni ugovor. |

### Preostalo otvoreno

Ništa što blokira M0–M3.

1. Uzorci XML izvoda po banci — potrebni pred procenu M4, ne ranije.

---

## 6. Redosled rada

Preporučeni redosled je M0 → M1 → M2 → M3 → M3a, pa tek onda M4 → M5 → M6.

Razlog je što M2 i M3 zajedno već pokrivaju svakodnevni posao — mesečno fakturisanje —
pa aplikacija može da uđe u upotrebu dok se praćenje naplate još razvija. Obrnut redosled
znači duže čekanje na prvu upotrebljivu verziju, bez dobitka.
