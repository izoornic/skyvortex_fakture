---
paths:
  - 'app/Actions/PartnerGroups/**'
---

# Partner Groups

## Objedinjena pošiljka uzima samo period, nikad filtere ekrana
`CollectGroupInvoices` je jedina definicija šta ulazi u objedinjeni PDF grupe: izdate fakture partnera grupe za godinu i mesec (`countable()` — izdata, delimično plaćena, plaćena). Nacrti i stornirane se izostavljaju; `excluded()` uz svaku vraća i razlog, jer ekran mora da kaže zašto faktura koju korisnik vidi nije u dokumentu.

Sa spiska faktura i sa ekrana pošiljke prenosi se **samo period**. Status i pretraga se nikad ne prosleđuju — otkucan pojam u pretrazi bi tiho izbacio fakturu iz pošiljke koju kupac očekuje.

Fakture se crtaju u jednom prolazu kroz `pdf/partials/invoice-body`, ne spajaju se gotovi PDF-ovi (dompdf to ne ume, a spajanje bi tražilo `setasign/fpdi`). Podaci svake fakture se sklapaju pre iscrtavanja da bi greška prijavila broj i partnera, umesto da ceo dokument padne bez imena.
