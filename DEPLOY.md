# PlayerPath live zetten

Dit is het deel van fase 12 dat niet in code kan staan, omdat het om accounts
en servers gaat. Werk het van boven naar beneden af; onderaan staat wat je na
elke deploy nog doet.

Na elke stap kun je controleren waar je staat:

```bash
php artisan playerpath:check
```

Dat commando kent het verschil tussen **blokkerend** (rood, hier moet je iets
mee) en een **aandachtspunt** (geel, kan bewust zo zijn). Zolang er iets roods
staat, is de omgeving niet klaar voor een echte school.

---

## 1. Server

**Hetzner via Laravel Forge.** Een gedeelde CX-server in Falkenstein of Neurenberg
is ruim voldoende voor de eerste scholen; het is een Laravel-app met een paar
honderd gebruikers, geen videoplatform.

**Of helemaal geen eigen server.** Draait er al een Forge-server met andere
sites, dan kan PlayerPath daar als extra site bij — dat kost niets extra en
scheelt een plan-upgrade. Wat je dan wél regelt:

- **PHP 8.4 erbij installeren** (Server → PHP) en per site instellen; de andere
  sites blijven op hun eigen versie staan.
- **Eigen Redis-databases** (`REDIS_DB`, `REDIS_CACHE_DB`). De sleutelprefix is
  al uniek per app, dus botsen doet het niet, maar met eigen databases kan
  `cache:clear` nooit de app van een ander raken.
- **Weten dat je buren deelt.** De queue-worker is een blijvend PHP-proces, en
  een piek bij PlayerPath merkt de site ernaast. Voor de eerste scholen is dat
  prima; groeit het, dan verhuis je naar een eigen server — dat is een site
  aanmaken en de DNS omzetten, geen verbouwing.

Waarom Hetzner: de gegevens van kinderen blijven binnen de EU, en dat is bij de
AVG geen detail. Kies bij Forge dus ook een **EU-regio** voor de database en
voor eventuele object storage.

Op de server:

- PHP 8.4
- MySQL 8
- Redis (voor de queue en de cache)
- Node alleen tijdens het bouwen; de gebouwde assets gaan mee in de deploy

## 2. Domein en certificaat

### Begin zonder subdomeinen

Eén A-record bij je registrar (`@` → het IP van de server), servernaam
`playerpath.nl`, en een gewoon Let's Encrypt-certificaat uit Forge. Klaar.

**Laat `APP_DOMAIN` dan leeg.** `Branding::fromHost()` leidt zonder die
instelling bewust géén school uit het adres af; de huisstijl komt dan uit het
ingelogde account, en dat werkt overal in de app. Een school bereikt haar
aanmeldpagina op `playerpath.nl/inschrijven/{slug}`.

Wat je zo mist is precies één ding: de **inlogpagina** draagt het merk van
PlayerPath in plaats van dat van de school, want zonder ingelogde gebruiker valt
er niets af te leiden. Dat is de prijs, en voor een eerste school is die laag.

### Later: een eigen adres per school

Wil een school `keepersschool-rob.playerpath.nl`, dan vraagt dat twee dingen:

1. Een **wildcard-DNS-record**: `*.playerpath.nl` → het IP van de server.
2. Een **wildcard-certificaat** — Let's Encrypt geeft dat alleen af via een
   DNS-challenge, en Forge moet daarvoor zelf een record kunnen wegschrijven.
   Een certificaat per subdomein aanvragen werkt niet: er komen steeds nieuwe
   scholen bij.

Dat tweede punt is de enige reden dat de DNS bij een provider moet staan die
Forge kan aansturen (Cloudflare bijvoorbeeld). Verhuis dan alleen de
**nameservers**; de registratie blijft waar hij is.

1. Cloudflare (gratis) → Add a site → `playerpath.nl`.
2. **Loop de gescande records na vóór je de nameservers omzet.** Staat er
   e-mail op dit domein, dan moeten de MX- en TXT-records mee — anders valt je
   mail stil op het moment dat de verhuizing doorkomt.
3. Nameservers bij de registrar vervangen door die van Cloudflare.
4. Twee A-records naar het IP van de server: `@` en `*`.
5. **Proxy-wolkje op grijs (DNS only).** Met de proxy aan termineert Cloudflare
   zelf het SSL en loopt de uitgifte via Forge in de war.
6. API-token in Cloudflare (template *Edit zone DNS*, beperkt tot dit domein);
   die vult Forge in bij Let's Encrypt → DNS-validatie.
7. Servernaam in Nginx op `playerpath.nl *.playerpath.nl`, en pas dan
   `APP_DOMAIN=playerpath.nl` invullen.

Er verandert geen regel code: het is een instelling en een ander certificaat.

> Het subdomein bepaalt alleen het logo, de naam en de kleur. Welke gegevens
> iemand ziet hangt uitsluitend af van zijn account. Dat is een harde regel —
> zie CLAUDE.md 3.1 — en er staat een test op die precies dit controleert.

## 3. Omgeving (.env)

**`.env.production.example` in de repo is het volledige bestand**, met uitleg
per blok. Plak het in Forge (Site → Environment) en vul de lege waarden in.
Hier staan alleen de keuzes die je niet mag omdraaien:

- **`APP_DEBUG=false` is niet onderhandelbaar.** Met debug aan krijgt iedere
  bezoeker bij een fout je stacktrace, je databasenaam en je
  omgevingsvariabelen te zien.
- **`LOG_LEVEL=warning`**, niet `debug`. Een productielogboek vol queries is
  onleesbaar, en het staat vol met gegevens van kinderen.
- **`APP_DOMAIN` moet gezet zijn**, anders leidt `Branding::fromHost()` geen
  enkel subdomein af en ziet elke school het merk van PlayerPath.
- **`SESSION_DOMAIN` bepaalt of één keer inloggen genoeg is.** Laat je hem
  leeg, dan hoort de sessie bij precies één host, en moet een ouder die vanuit
  een mail op `playerpath.nl` binnenkomt opnieuw inloggen zodra hij op het
  subdomein van zijn school komt. Zet hem daarom op `.playerpath.nl`. Dat is
  veilig: het subdomein bepaalt alleen de huisstijl, welke gegevens iemand
  ziet hangt uitsluitend van zijn account af (CLAUDE.md 3.1).

**Mail bij Brevo:** zet SPF, DKIM en DMARC voor `playerpath.nl` klaar vóór de
eerste school. Zonder die records belandt een wachtwoord-vergeten-mail in de
spammap, en dan kan een ouder niet inloggen.

1. Brevo → Senders, Domains & Dedicated IPs → **Domains** → `playerpath.nl`
   toevoegen. Brevo geeft dan twee `TXT`-records (DKIM en een verificatie).
2. Zet er zelf een SPF- en een DMARC-record bij:

   | Naam | Type | Waarde |
   |---|---|---|
   | `@` | TXT | `v=spf1 include:spf.brevo.com ~all` |
   | `_dmarc` | TXT | `v=DMARC1; p=none; rua=mailto:dmarc@playerpath.nl` |

   Begin met `p=none`: dat rapporteert wel en weigert niets. Pas als je een
   paar weken schone rapporten hebt kun je naar `p=quarantine`.
3. Maak de SMTP-sleutel onder **SMTP & API**; die vult `MAIL_USERNAME` en
   `MAIL_PASSWORD`.

De afzendernaam van elke mail is de school; het afzenderadres blijft van het
platform. Een eigen afzenderadres per school kan pas als die school haar eigen
DNS-records aanlevert.

**Controleer de mail vóór de eerste school.** De vormgeving bekijk je **lokaal**
met `php artisan mail:preview` — dat is een ontwikkelhulpmiddel en werkt niet op
de server, want het bouwt zijn voorbeeldwereld met factories en die hangen aan
`fakerphp/faker` uit `require-dev`.

Op de server test je het echte pad: maak een school met `school:create`, vraag
een wachtwoord aan via wachtwoord-vergeten, en kijk of die mail aankomt, niet in
de spam belandt, en de naam van de school draagt. Dat is meteen de test van
Brevo, van je DNS-records én van de queue-worker in één keer.

## 4. Deploy-script (Forge)

```bash
cd /home/forge/playerpath.nl
git pull origin main

composer install --no-dev --optimize-autoloader

npm ci
npm run build

php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force

php artisan storage:link
php artisan playerpath:icons

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart
```

**`db:seed --class=RoleSeeder --force` hoort er elke keer in.** De rollen
(eigenaar, trainer, ouder, speler) komen niet uit de migraties maar uit de
seeder, en zonder die rollen weigert `school:create`. De seeder gebruikt
`findOrCreate`, dus vaker draaien kan geen kwaad, en een rol die er later bij
komt staat na de eerstvolgende deploy vanzelf klaar. `--force` is verplicht:
zonder die vlag vraagt Laravel op productie om een bevestiging, en in het
Commands-scherm van Forge wacht zo'n vraag eindeloos op een antwoord.

`queue:restart` is makkelijk te vergeten en levert het vervelendste soort bug
op: workers die oude code blijven draaien, dus meldingen die verwijzen naar een
scherm dat niet meer bestaat.

## 5. Achtergrondprocessen

**Queue-worker** (Forge → Daemons), anders komt er geen enkele mail aan:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

**Scheduler** (Forge → Scheduler, elke minuut):

```bash
php artisan schedule:run
```

Die verzorgt drie dingen, in deze volgorde:

| Tijd | Commando | Wat het doet |
|---|---|---|
| 08:00 | `payments:generate` | maakt de rekening voor de lopende termijn |
| 08:30 | `payments:collect` | schrijft af op bestaande incassomandaten |
| 09:00 | `payments:remind` | herinnert aan wat over de vervaldatum is |

De volgorde is bewust: een verse rekening moet mee kunnen in de incasso en mag
niet een uur later al als achterstallig gelden.

## 6. Backups

Zet dagelijkse databasebackups aan (Forge kan dit naar een EU-bucket) en
bewaar ze **buiten** dezelfde server. Een backup op de schijf die stukgaat is
geen backup.

Test één keer een terugzetactie voordat de eerste school begint. Een backup
waarvan je niet weet of hij werkt, is een aanname.

## 7. Betalingen aanzetten

1. Mollie-account, organisatie geverifieerd.
2. Eerst de **testsleutel** (`test_...`) en de hele keten doorlopen:
   eerste betaling, mandaat, incasso, stornering.
3. Pas daarna de livesleutel.
4. De webhook-URL is `https://playerpath.nl/webhooks/mollie`. Die moet publiek
   bereikbaar zijn; hij heeft bewust geen inlog en geen CSRF-token nodig.

De hele betaalketen is getest tegen een nagebouwde provider, niet tegen Mollie
zelf. Doe stap 2 dus echt.

## 8. E-mailverificatie

Fortify heeft `emailVerification` aanstaan. Controleer bij de eerste
uitnodiging dat de mail aankomt en dat de link werkt — dit is het eerste wat
een nieuwe trainer of ouder van het systeem ziet.

## 9. Een school toevoegen

```bash
php artisan school:create
```

Dat maakt de school en de eigenaar. De eigenaar krijgt geen wachtwoord van jou:
hij kiest er zelf een via wachtwoord-vergeten. Daarna doet hij de rest zelf —
trainers uitnodigen, groepen maken, tarieven zetten, huisstijl kiezen.

Zelfregistratie staat uit en blijft uit: een school komt er alleen in via dit
commando.

## 10. Na de eerste dag

- Draai `php artisan playerpath:check` op de server.
- Kijk of de queue leegloopt (`php artisan queue:monitor` of Horizon).
- Controleer of er meldingen zijn blijven hangen in `failed_jobs`.
- Vraag de eerste trainer of het rapport-invulscherm op zijn telefoon in
  dertig seconden lukt. Zo niet, dan is dat het eerste wat je repareert — de
  rest van het product hangt eraan.
