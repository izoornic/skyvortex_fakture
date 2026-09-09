---
paths:
  - .cpanel.yml
---

# General

## Deploy na cPanel: putanje binarija, dozvole radnog direktorijuma i keš pre composer-a
Nalog je alt-php shared, ne ea-php. Composer je na `/usr/local/bin/composer` (`/opt/cpanel/composer/bin/composer` ne postoji — task vrati 127 i sruši ceo deploy pre `composer install`). `PHPBIN` mora ostati `/usr/local/bin/php`, podrazumevani PHP naloga: `public/.htaccess` nema `AddHandler` override, pa aplikacija prati MultiPHP Manager i onda kad hoster promeni verziju. Ne upisuj ea-php84/alt-php84 putanje.

`/bin/mkdir -p $DEPLOYPATH` radi pod umask-om deploy procesa i pravi direktorijum sa 700; LiteSpeed onda ne može da uđe u `fakture_app` i vraća 404 pre `public/index.php`. Zato odmah posle `mkdir`-a ide `chmod 711 $DEPLOYPATH` — bez njega je sajt mrtav, a document root izgleda ispravno.

Pre `composer install` obavezno obriši `bootstrap/cache/{packages,services,config,compiled}.php`, jer `package:discover` iz `post-autoload-dump` čita zatečeni keš.

Kopira se sa `cp -a *`, što namerno ne dira skrivene fajlove — `.env`, `.git`, `.ai`, `.claude` i `storage/` na serveru ostaju netaknuti. `public/build` se commituje (Node se na sharedu ne koristi), pa pre commita lokalno pokreni `npm run build`. Migracije se puštaju ručno.
