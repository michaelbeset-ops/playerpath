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

Waarom Hetzner: de gegevens van kinderen blijven binnen de EU, en dat is bij de
AVG geen detail. Kies bij Forge dus ook een **EU-regio** voor de database en
voor eventuele object storage.

Op de server:

- PHP 8.4
- MySQL 8
- Redis (voor de queue en de cache)
- Node alleen tijdens het bouwen; de gebouwde assets gaan mee in de deploy

## 2. Domein en certificaat

Elke school krijgt een eigen adres (`keepersschool-rob.playerpath.nl`). Dat
vraagt twee dingen:

1. Een **wildcard-DNS-record**: `*.playerpath.nl` → het IP van de server.
2. Een **wildcard-certificaat**. In Forge: Let's Encrypt met DNS-validatie
   (Cloudflare of een andere ondersteunde DNS-provider). Een gewoon certificaat
   per subdomein werkt niet, want er komen steeds nieuwe scholen bij.

Zet in de Nginx-site de servernaam op `playerpath.nl *.playerpath.nl`.

> Het subdomein bepaalt alleen het logo, de naam en de kleur. Welke gegevens
> iemand ziet hangt uitsluitend af van zijn account. Dat is een harde regel —
> zie CLAUDE.md 3.1 — en er staat een test op die precies dit controleert.

## 3. Omgeving (.env)

```dotenv
APP_NAME=PlayerPath
APP_ENV=production
APP_DEBUG=false
APP_URL=https://playerpath.nl
APP_DOMAIN=playerpath.nl

APP_LOCALE=nl
APP_FALLBACK_LOCALE=nl
APP_TIMEZONE=Europe/Amsterdam

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=playerpath
DB_USERNAME=forge
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@playerpath.nl
MAIL_FROM_NAME=PlayerPath

MOLLIE_KEY=live_...
```

`APP_DEBUG=false` is niet onderhandelbaar. Met debug aan krijgt iedere bezoeker
bij een fout je stacktrace, je databasenaam en je omgevingsvariabelen te zien.

**Mail bij Brevo:** zet SPF, DKIM en DMARC voor `playerpath.nl` klaar vóór de
eerste school. Zonder die records belandt een wachtwoord-vergeten-mail in de
spammap, en dan kan een ouder niet inloggen.

De afzendernaam van elke mail is de school; het afzenderadres blijft van het
platform. Een eigen afzenderadres per school kan pas als die school haar eigen
DNS-records aanlevert.

## 4. Deploy-script (Forge)

```bash
cd /home/forge/playerpath.nl
git pull origin main

composer install --no-dev --optimize-autoloader

npm ci
npm run build

php artisan migrate --force

php artisan storage:link
php artisan playerpath:icons

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart
```

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
