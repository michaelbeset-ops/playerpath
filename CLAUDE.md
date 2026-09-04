# PlayerPath — CLAUDE.md

Dit bestand is het kompas voor dit project. Lees het aan het begin van elke sessie.
Bij twijfel of tegenstrijdigheid: **dit bestand wint** van gewoontes uit andere projecten.

---

## 1. Wat het is

**PlayerPath** is een SaaS-platform voor **keeper- en voetbalscholen**.

Meerdere scholen (tenants) draaien in één systeem, elk met strikt eigen data.
Het onderscheidende deel is de keten **rapport → spelerskaart → voortgang**:
een trainer vult in ~30 seconden een rapport in, en de "FIFA-kaart" van de
speler verandert zichtbaar mee. Ouders en spelers zien groei over tijd.

Doelgroep-rollen: schooleigenaar, trainer, ouder, speler.

---

## 2. Stack

| Onderdeel | Keuze |
|---|---|
| Backend | Laravel (PHP 8.4) |
| Frontend | Inertia + Vue 3 (`<script setup>`, Composition API) |
| Styling | Tailwind CSS |
| Build | Vite |
| Auth | Laravel Fortify |
| Rollen/rechten | spatie/laravel-permission |
| Admin-UI componenten | PrimeVue |
| Betalingen | Mollie + Laravel Cashier (**pas in fase 7**, niet eerder installeren) |
| Lokaal | Laravel Herd + **SQLite** |
| Productie (later) | Hetzner via Forge, MySQL + Redis, mail via Brevo |

---

## 3. Harde regels

Dit zijn geen richtlijnen maar voorwaarden. Code die hier tegenin gaat is fout.

### 3.1 Multi-tenancy (belangrijkste regel)

- **Elke tabel met klantdata krijgt een `school_id`.** Ook koppeltabellen.
- Scoping gebeurt **altijd server-side**, via **één centrale scope-laag**.
  Niet per controller opnieuw, niet per query handmatig.
- **Nooit** data verbergen in de UI en denken dat het daarmee beveiligd is.
- Een school mag **nooit** data van een andere school kunnen zien, lezen,
  raden via een ID in de URL, of via een API-response terugkrijgen.

#### Hoe het werkt (fase 1)

| Onderdeel | Bestand | Wat het doet |
|---|---|---|
| Actieve school | `app/Support/Tenancy/Tenancy.php` | Singleton met de school van deze request |
| Middleware | `app/Http/Middleware/SetCurrentSchool.php` | Leidt de school af uit de **ingelogde gebruiker** |
| Global scope | `app/Models/Scopes/SchoolScope.php` | Filtert elke query op `school_id` |
| Trait | `app/Models/Concerns/BelongsToSchool.php` | Scope + `school_id` invullen + vastzetten |

**Bij elk nieuw model met klantdata: `school_id` in de migratie, de
`BelongsToSchool`-trait op het model, en een policy. Alle drie.**

Drie eigenschappen die je niet mag weghalen:

1. **De school komt nooit uit invoer.** Niet uit de URL, een subdomein of een
   formulierveld — alleen uit het ingelogde account. Er valt dus niets aan te
   knoeien.
2. **Fail-closed.** Is er geen actieve school, dan levert een query *niets* op
   (`1 = 0`) en gooit opslaan een fout. Een vergeten middleware mag nooit per
   ongeluk alle scholen openzetten.
3. **`school_id` ligt vast.** Hij wordt automatisch ingevuld bij aanmaken, staat
   niet in `$fillable`, en wijzigen op een bestaand record gooit een fout.

Koppeltabellen krijgen hun `school_id` via `withPivotValue('school_id',
$this->school_id)` op de relatie. Dat vult hem in én filtert erop.

`Tenancy::withoutScope()` en `Model::withoutSchoolScope()` bestaan voor bewuste
beheer-acties (seeders, platformbeheer). **Nooit in een controller gebruiken.**

#### Valkuil: validatieregels kennen de global scope niet

`Rule::exists()` en `Rule::unique()` gaan **rechtstreeks naar de database** en
gaan dus buiten Eloquent en de global scope om. Zonder extra `where` accepteert
een formulier gewoon het id van een record van een andere school.

Voeg daarom altijd de school toe:

```php
Rule::exists('groups', 'id')->where('school_id', app(Tenancy::class)->id())
Rule::unique('groups', 'name')->where('school_id', app(Tenancy::class)->id())
```

Hetzelfde geldt voor `User`, dat sowieso geen global scope heeft: begrens
gebruikers-ids expliciet op `school_id`.

#### Valkuil: koppeltabellen bij eager loading

Relaties gebruiken `withPivotValue('school_id', $this->pivotSchoolId())`. Die
helper bestaat omdat Eloquent bij **eager loading** de relatie op een leeg model
bouwt, waar `school_id` nog `null` is — een letterlijke `$this->school_id` gooit
daar een exception. De helper valt terug op de actieve school, en anders op `0`:
dat bestaat niet, dus de query levert niets op en een insert faalt. Fail-closed.

#### Route model binding draait eerder dan de middleware

`SubstituteBindings` zit in de web-groep **voor** `SetCurrentSchool`. Zonder
maatregel zou elke URL met een `{player}` een 404 geven, omdat de scope dan nog
fail-closed dichtstaat. Daarom heeft `Tenancy` een terugval-resolver
(`resolveUsing`, gezet in `AppServiceProvider`) die de school alsnog uit het
ingelogde account haalt. De bron blijft dus hetzelfde; `SetCurrentSchool` blijft
de plek waar geweigerd wordt.

#### Twee bewuste uitzonderingen

- **`User` heeft géén global scope.** Inloggen moet een gebruiker op e-mailadres
  kunnen vinden vóórdat er een school bekend is. In plaats daarvan: query
  gebruikers via `$school->users()` of `User::ofCurrentSchool()`, en `UserPolicy`
  weigert alles buiten de eigen school.
- **De tabellen van spatie/laravel-permission hebben geen `school_id`.** De vier
  rollen zijn platformbrede begrippen, geen klantdata. De koppeling
  gebruiker↔rol is per gebruiker, en die hoort al bij één school.

### 3.2 Geld

- Bedragen altijd als **integers in centen** (`bigInteger`), nooit als float.
- Kolomnamen eindigen op `_cents` (bijv. `amount_cents`).
- Rekenen doe je in centen; pas bij weergave formatteer je naar euro's.

### 3.3 Taal

- **Alles volledig in het Nederlands**: UI-teksten, e-mails, validatiemeldingen,
  foutmeldingen, bevestigingen, lege-staten.
- Code zelf (variabelen, methodes, tabellen, kolommen) blijft **Engels** —
  dat is Laravel-conventie en houdt de code leesbaar.
- Nederlandse vertalingen in `lang/nl/`. `APP_LOCALE=nl`, `APP_FALLBACK_LOCALE=nl`.
- Datums/tijden in Nederlands formaat (`d-m-Y`, 24-uurs), tijdzone `Europe/Amsterdam`.

### 3.4 Rollen

Vier rollen via spatie/laravel-permission:

- `eigenaar` — beheert de school, ziet alles binnen de eigen school
- `trainer` — vult rapporten in, ziet zijn groepen
- `ouder` — ziet alleen de kaart/voortgang van het eigen kind
- `speler` — ziet alleen de eigen kaart

Autorisatie loopt via **policies** (`app/Policies/`), niet via
`if ($user->role === ...)` in views. Elke policy-check begint met "zelfde
school" — dat staat bewust náást de global scope: twee sloten op dezelfde deur.

Zelfregistratie staat **uit**. Scholen en eigenaren zet je op met
`php artisan school:create`; trainers en ouders worden later uitgenodigd door
de eigenaar.

---

## 4. Huisstijl

PlayerPath heeft **twee kanten met één merk**. Dit is een harde regel, geen smaak:

| Kant | Voor wie | Thema |
|---|---|---|
| **Admin / dashboard** | eigenaar, trainer | **Licht** — rustige werkvloer waar je uren op kijkt |
| **Speler / ouder + spelerskaart** | speler, ouder | **Donker** — premium, in de geest van een FIFA-kaart |

Fel groen is in beide kanten de accent- en actiekleur. Goud hoort bij de
donkere kant (kaart, badges, mijlpalen) en wordt in de admin-kant vrijwel niet
gebruikt.

### Hoe je het thema aanzet

Licht is de standaard: `:root` in `resources/css/app.css` bevat de lichte
tokens. Donker zet je aan met de klasse **`theme-donker`** op een
schermvullende wrapper — zie `layouts/auth/AuthSimpleLayout.vue` als voorbeeld.

**Gebruik altijd de tokens**, nooit een hardgecodeerde kleur: `bg-background`,
`bg-card`, `text-foreground`, `text-muted-foreground`, `border-border`,
`bg-primary`, `text-gold`. Zo klopt een component in beide thema's zonder
`dark:`-varianten. Staat er ergens `bg-white`, `text-black`, `text-red-600` of
`dark:`, dan is dat fout.

### Kleuren — admin (licht)

| Rol | Kleur | Hex |
|---|---|---|
| Achtergrond / werkvloer | Gebroken wit, geen hard wit | `#F8F9FA` |
| Kaarten | Wit, met subtiele rand + lichte schaduw | `#FFFFFF` |
| Rand | Lichtgrijs | `#E2E8F0` |
| Zijbalk | Iets dieper grijs, zodat de werkvloer afsteekt | `#EBEEF2` |
| Primair / actie | Groen (iets dieper, leesbaar op wit) | `#1BB85E` |
| Tekst primair | Donker marineblauw | `#0F172A` |
| Tekst secundair | Grijsblauw | `#5A677D` |

Kaarten krijgen `border border-border` **plus** `shadow-sm`. Alleen een rand is
te vlak; alleen een schaduw is te zwevend.

#### Groen betekent iets

Het groen uit het logo is het enige accent op de admin-kant, en het is
**informatie, geen decoratie**:

- een kerncijfer met een echte waarde krijgt een groen getal, een groen
  streepje bovenaan de kaart en een groen getint icoon;
- een kaart zonder waarde (nog niet gebouwd, of nul) blijft **grijs**.

Zo zie je in één oogopslag wat leeft. Zou alles groen zijn, dan zegt de kleur
niets meer. Gebruik geen extra accentkleuren om schermen "levendiger" te maken.

Kerncijfers gebruik je via `components/StatCard.vue` — label, waarde, hint,
icoon en optioneel een `href`. Elk cijfer krijgt een eigen lucide-icoon
(spelers `Users`, rapporten `ClipboardList`, trainingen `CalendarDays`).

### Kleuren — speler/ouder (donker)

| Rol | Kleur | Hex |
|---|---|---|
| Basis / achtergrond | Bijna-zwart donkerblauw | `#0A0F1C` |
| Surface (kaarten, panelen) | Donkerblauw | `#111A2E` |
| Surface hoog / randen | Donkerblauw licht | `#1C2942` |
| Primair / actie | Fel groen | `#22E06B` |
| Accent (kaart, badges, mijlpalen) | Goud/metallic | `#D4AF37` |
| Tekst primair | Bijna-wit | `#F1F5F9` |
| Tekst secundair | Grijsblauw | `#94A3B8` |

Op donker komt diepte van kleurverschil, niet van harde schaduwen.

### Gedeelde schermen

Inloggen, registreren en de startpagina zijn de gedeelde voordeur en staan nu
op **donker** — dat is de merkbeleving die je als eerste ziet. Verandert dat
inzicht, dan is het één klasse omzetten.

### Typografie

- Font: **Inter** (fallback: system-ui, sans-serif).
- Cijfers op de spelerskaart: zwaar (700–800), strak, `tabular-nums`
  (utility-klasse `tabular`).

### Vorm

- Afronding: `rounded-xl` (12px) standaard, `rounded-2xl` (16px) voor de
  spelerskaart en grote panelen, `rounded-lg` voor inputs.
- Ruim gebruik van witruimte (spacing-schaal 4/6/8).

## 5. Datamodel

| Model | Hoort bij | Belangrijkste velden |
|---|---|---|
| `School` | — (is de tenant) | `name`, `slug`, `is_active` |
| `User` | school | `name`, `email`, rol via spatie |
| `Player` | school | `first_name`, `last_name`, `date_of_birth`, `position`, `user_id?` |
| `Group` | school | `name`, `age_category`, `is_active` |
| `Report` | school | `player_id`, `trainer_id`, `reported_on`, `note` |
| `ReportScore` | school | `report_id`, `category`, `score` (1-10) |

Beheerschermen (fase 3): spelers en groepen zijn volledig te beheren door de
**eigenaar**; de trainer mag alles zien maar niets wijzigen. Ouders koppel je
op de spelersdetailpagina. Omdat zelfregistratie dichtstaat maakt de eigenaar
het ouder-account aan; de ouder krijgt een e-mail om **zelf** een wachtwoord te
kiezen, via de bestaande wachtwoord-vergeten-route. Er wordt dus nooit een
wachtwoord gezet dat iemand anders kent.

Verwijderen versus deactiveren: een speler die stopt zet je op **niet-actief**
(hij blijft bewaard, telt niet mee). Definitief verwijderen bestaat wel, maar
neemt de rapporten mee en staat daarom apart onderaan de detailpagina. Een
groep verwijderen raakt de spelers nooit: alleen de indeling verdwijnt.

Relaties:

- `Player` ↔ `Group`: **veel-op-veel** (`group_player`). Een speler kan in
  meerdere groepen zitten (keeperstraining én veldtraining). Indelen is
  **altijd handwerk** — nooit automatisch op leeftijd.
- `Player` ↔ `User` (ouder): veel-op-veel (`guardian_player`), met
  `relationship` (moeder/vader/verzorger). Op `User` heet dit `children()`.
- `Player` → `User`: optioneel eigen inlogaccount van de speler zelf.

Twee afspraken die je niet moet omdraaien:

- **Leeftijd staat niet op de speler.** De speler heeft een `date_of_birth`;
  leeftijd leid je daaruit af (`$player->age`), zodat het na een seizoenswissel
  vanzelf klopt. De **leeftijdscategorie** ("Onder 12") hoort bij de **groep**.
- **Positie** is een enum (`App\Enums\PlayerPosition`): `keeper` of `field`.
  In de database Engels, in de UI het Nederlandse label.

### Rapport en spelerskaart (fase 2)

De categorieen staan in `App\Enums\ReportCategory`, zes per positie:

- **Keeper:** reflexen, uitkomen, voetenwerk, 1-op-1, hoge ballen, communicatie
- **Veldspeler:** techniek, inzicht, passing, afwerking, snelheid, mentaliteit

Zes is bewust: dat past op een scherm zonder scrollen, en dat is de voorwaarde
voor het 30-seconden-rapport.

**De schaal is 1 t/m 10**, zoals een rapportcijfer. Op de kaart wordt dat maal
tien: een 8 leest als 80. Zo denkt de trainer in cijfers die hij kent, en ziet
de speler een FIFA-achtig getal.

Doorrekenen gebeurt in `App\Support\PlayerCard\CalculatePlayerCard`:

1. Per categorie tellen de **laatste 3 rapporten** mee. Een mindere training
   verpest de kaart niet, maar echte groei is binnen een paar rapporten zichtbaar.
2. Sub-score = gemiddeld cijfer x 10.
3. Overall = gemiddelde van de sub-scores, afgerond.
4. Zonder rapporten: `null`, niet 0. Een lege kaart is geen slechte kaart.

De uitkomst wordt opgeslagen op `players` (`overall_rating`,
`category_ratings`, `rated_at`). Dat is een **momentopname**: de waarheid staat
in `reports`. Die kolommen staan niet in `$fillable` en worden alleen door
`CalculatePlayerCard::refresh()` gezet — nooit met de hand.

**Het rapport-invulscherm is de belangrijkste UX van de app.** Wat het snel
houdt, en dus niet mag sneuvelen:

- de cijfers van het vorige rapport staan **voorgevuld**; de trainer past alleen
  aan wat veranderd is;
- een cijfer is **een tik** op een grote knop, geen dropdown of schuifje;
- cijfertoetsen 1-9 en 0 vullen de actieve rij en springen door naar de volgende;
- de cijferknoppen zijn minimaal 44px hoog, ook op telefoon (`h-11`), want dit
  scherm wordt langs de lijn op een telefoon gebruikt;
- opslaan zit in een vaste balk onderaan, binnen duimbereik;
- de toelichting is optioneel en breekt het ritme niet.

## 6. Werkwijze

- **Fase voor fase.** Het bouwplan staat in `bouwplan-keepersplatform-claude-code.md`
  (fase 0 t/m 8). Begin een fase pas als de vorige werkt en getest is.
- **Nooit vooruitlopen.** Bouw alleen wat in de huidige fase staat.
- Kleine, werkende stappen. Na elke werkende stap: **git commit**.
- Bij onduidelijkheid of dubbelzinnigheid: **eerst vragen, niet gokken**.
- Na afronding van een fase: samenvatten hoe het getest kan worden, en
  **wachten op akkoord** voor de volgende fase.

### Fase-status

- [x] Fase 0 — Projectopzet & fundament
- [x] Fase 1 — Datamodel & multi-tenancy
- [x] Fase 2 — Rapport → spelerskaart
- [x] Fase 3 — Spelers- & groepsbeheer
- [ ] Fase 4 — Planning & aanwezigheid
- [ ] Fase 5 — Voortgang & ouder-ervaring
- [ ] Fase 6 — Eigenaar-dashboard
- [ ] Fase 7 — Betalingen (Mollie + Cashier)
- [ ] Fase 8 — Productie & lancering

---

## 7. Principes

- **Fundament vóór schermen.** Fase 0 en 1 bepalen alles daarna.
- **Bouw alleen wat elke school kan gebruiken.** School-specifieke wensen worden
  een instelling (aan/uit), nooit een aparte versie of een `if ($school->id === 3)`.
- **De rapport-invoer is de belangrijkste UX.** Kan een trainer het niet in
  ~30 seconden, dan blijven de kaarten leeg en stort het product in.
- **Betalingen laatst en apart.** Geen Mollie/Cashier-code vóór fase 7.

---

## 8. Praktisch (lokaal)

- PHP/Composer komen van Herd; draai die commando's via **PowerShell**, niet via bash.
- Database: **SQLite** (`database/database.sqlite`). Geen MySQL lokaal.
- Server: Herd serveert de map automatisch. Assets: `npm run dev`.
- Migraties: `php artisan migrate`. Verse start: `php artisan migrate:fresh --seed`.
