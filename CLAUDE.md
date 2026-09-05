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

#### Het menu komt van de server

`App\Support\Navigation\MainNavigation` bepaalt welke menu-items je ziet, op
basis van de **policies**. De Vue-kant vertaalt alleen de iconennaam naar een
component. Dat is geen cosmetica: een hardgecodeerd menu liet een ouder
Spelers, Groepen en Rapporten zien die allemaal 403 gaven.

**Nieuw scherm erbij? Voeg het toe aan `MainNavigation`, niet aan
`AppSidebar.vue`.** `NavigationTest` loopt per rol elk getoond item echt af.

Elk item heeft een `section` (School of Financieel); `NavMain` groepeert erop.
Een verlopen sessie (419) wordt in `bootstrap/app.php` afgevangen met een
melding in plaats van een foutpagina.

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
icoon, optioneel een `href` en een **`tone`**. Die laatste is er omdat groen als
"goed" leest: een cijfer dat om actie vraagt (achterstallig, gestorneerd) krijgt
`tone="warning"` of `tone="danger"`, anders zegt de kleur het tegendeel van wat
er staat. Elk cijfer krijgt een eigen lucide-icoon
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
| `Training` | school | `group_id`, `starts_at`, `ends_at`, `location` |
| `Attendance` | school | `training_id`, `player_id`, `registration`, `status` |

`Player` heeft daarnaast `share_token` en `shared_at` voor de publieke kaart.

| Model | Hoort bij | Belangrijkste velden |
|---|---|---|
| `Plan` | school | `name`, `amount_cents`, `interval` |
| `Subscription` | school | `player_id`, `plan_id?`, `amount_cents`, `interval`, `status` |
| `Payment` | school | `player_id`, `subscription_id?`, `amount_cents`, `status`, `due_on` |

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

#### Speler versus gebruiker

Dit zijn **twee verschillende dingen** en die moet je niet samenvoegen:

- Een **speler** is een profiel in `players`. De school maakt hem aan; een eigen
  inlog (`players.user_id`) is **optioneel**. Een keeper van acht heeft geen
  e-mailadres, maar staat wel op de kaart — de ouder heeft het account.
- Een **trainer** of **ouder** is altijd een account in `users`.

Het menu-item **Gebruikers** (`/users`) toont ze samen in drie tabbladen:
spelers, trainers en ouders. Trainers nodig je daar uit; ouders koppel je op de
pagina van een speler, omdat een ouder zonder kind niets betekent.

Een **training** heeft nul of meer **trainers** (`training_user`). Dat is
**informatief**: het bepaalt niet wie er bij mag. Elke trainer ziet het hele
rooster en kan overal afvinken, want anders loopt invallen en ruilen vast.

**Rapporten overleven hun trainer.** `reports.trainer_id` is nullable met
`nullOnDelete`; een trainer verwijderen laat zijn rapporten staan. Die historie
hoort bij de speler, niet bij de trainer.

Twee afspraken die je niet moet omdraaien:

- **Leeftijd staat niet op de speler.** De speler heeft een `date_of_birth`;
  leeftijd leid je daaruit af (`$player->age`), zodat het na een seizoenswissel
  vanzelf klopt. De **leeftijdscategorie** ("Onder 12") hoort bij de **groep**.
- **Positie** is een enum (`App\Enums\PlayerPosition`): `keeper` of `field`.
  In de database Engels, in de UI het Nederlandse label.

### Betalingen (fase 7, nog niet aangesloten)

De administratie staat er volledig; **Mollie is bewust nog niet gekoppeld**.
De app praat alleen met `Support/Payments/PaymentGateway`, nooit rechtstreeks
met een provider. Nu hangt daar `NotConnectedGateway` aan; aansluiten is later
een `MollieGateway` schrijven en die in `AppServiceProvider` binden.

Zolang er geen provider is:

- **maakt de app zelf geen betalingen aan.** Een abonnement vastleggen is
  administratie; er wordt niets geïncasseerd. Geen nepbetalingen, ooit.
- **zegt elk scherm dat eerlijk**, via `GatewayNotice`.
- kan de eigenaar een betaling **met de hand** op betaald zetten. Dat blijft ook
  daarna nuttig, voor overboekingen en contant.

Twee regels over geld die je niet moet omdraaien:

- **Alles in centen**, ook door de hele keten heen. `Support/Money/Money` doet
  de omzetting: `toCents()` gaat via string en `round()`, want `12.50 * 100`
  geeft in floating point `1249.9999999999998`. Precies daarom is geld geen float.
- **Een abonnement bewaart zijn eigen bedrag en interval**, los van het tarief.
  Verhoogt de school later haar prijs, dan verandert een lopend abonnement niet
  mee — anders zou een tariefwijziging met terugwerkende kracht ingaan.

Rollen: tarieven, abonnementen en het betaaloverzicht zijn van de **eigenaar**.
Een trainer komt er niet bij. Een ouder heeft een eigen scherm (`/billing`) met
alleen het abonnement en de betalingen van zijn eigen kind.

### Het eigenaar-dashboard (fase 6)

De cijfers staan in `Support/Dashboard/SchoolDashboard`, niet in de controller.
Drie afspraken die je niet moet omdraaien:

- **Alleen cijfers die echt bestaan.** Liever een leeg vak met uitleg dan een
  getal dat nergens op slaat.
- **Opkomst telt alleen wat de trainer echt afvinkte.** "Niet afgevinkt" is
  geen "afwezig"; die spelers vallen buiten de breuk.
- **"Vraagt om aandacht" is het belangrijkste blok**: spelers zonder rapport in
  30 dagen. Dat laat zien waar het product stilvalt, en een lege kaart is
  precies waarom een ouder afhaakt.

Snelle acties volgen de policies, net als het menu: een trainer krijgt geen
knop "Speler toevoegen" te zien. Het **financiële vak is van de eigenaar** en
staat er bewust leeg bij tot fase 7 — geen voorbeeldcijfers.

### Voortgang, meldingen en de kaart (fase 5)

**Voortgang** (`Support/PlayerCard/PlayerProgress`) toont het cijfer van elk
**rapport afzonderlijk**, niet het doorgerekende kaartgemiddelde. Anders zie je
een afgevlakte lijn in plaats van wat de trainer die dag opschreef. Vanaf twee
rapporten is er een grafiek; daarvoor niet.

**Tijdlijn en mijlpalen worden afgeleid**, niet opgeslagen. Er is geen
gebeurtenissen-tabel die uit de pas kan lopen met de werkelijkheid, en een
nieuwe badge kost één regel in `PlayerBadges`.

**Meldingen** gaan via `NieuwRapport` naar de ouders en de speler zelf, in de
app en per e-mail. De notificatie is `ShouldQueue`: het opslaan van een rapport
mag nooit wachten op een mailserver — dat scherm moet in dertig seconden klaar
zijn. De melding gaat er **na** de transactie uit, zodat een mislukte opslag
nooit alsnog een e-mail oplevert. Lokaal draai je `php artisan queue:work`.

**Het dashboard verschilt per rol.** Eigenaar en trainer zien de school; ouder
en speler hun eigen kind. Eén gedeeld dashboard toonde een ouder schoolbrede
cijfers en knoppen die hij niet mocht gebruiken.

#### De publieke deel-link

`/kaart/{token}` is de **enige route zonder inlog**, en het gaat om gegevens van
een kind. Wat die pagina veilig houdt, en dus niet weg mag:

- delen staat standaard **uit** en moet per speler aangezet worden;
- de link is een willekeurig token van 48 tekens, niet het id van de speler;
- uitzetten wist het token — een gedeelde link is daarna direct dood;
- er staat alleen **voornaam + initiaal**, positie en cijfers op. Geen
  achternaam, leeftijd, geboortedatum, school, groep, notities of ouders;
- `HandleInertiaRequests` zet de gedeelde props voor deze route **expliciet
  leeg** (geen `school`, `nav`, `auth`), want Inertia's shared store is statisch;
- `PreventSearchIndexing` zet `X-Robots-Tag` als **header**; een meta-tag via
  Inertia komt er pas door JavaScript in en daar reken je bij een crawler niet op;
- wie mag delen: de **eigenaar** en de **ouders van dit kind**. De trainer niet —
  die beslist niet of andermans kind op internet komt.

### De spelerskaart als verzamelkaart

`components/PlayerCardVisual.vue` is de kaart zelf, gebruikt op de kaartpagina
en op de publieke deel-pagina. Eén component, zodat die twee nooit uit elkaar
lopen.

- **Eigen identiteit, geen FUT-kopie**: geen schild, geen vlag, geen clublogo.
  Wel een groot overall-cijfer, zes stats met drieletterige afkortingen en een
  medaillon met initialen.
- **Het niveau bepaalt de look** via CSS-variabelen per tier (`pp-tier-*`):
  brons, zilver, goud, elite. Elite heeft als enige een bewegende schittering,
  uit bij `prefers-reduced-motion`. Een nieuw niveau is één CSS-blokje.
- **Keeper en veldspeler zien er anders uit**: keepers krijgen diagonale
  handschoen-strepen, veldspelers veldlijnen. Subtiel, in de achtergrond.
- **Mobiel-first**: maximaal 360px breed, schaalt daaronder mee; onder 360px
  wordt het cijfer en het medaillon kleiner.

De kaart is bewust donker, ook binnen de lichte admin-schil: dat is de
speler/ouder-kant van het merk (hoofdstuk 4).

### Inschrijvingen

Elke school heeft een **openbaar inschrijfformulier** op `/inschrijven/{slug}`.
Dat is, naast de gedeelde kaart, de enige route zonder inlog; de school komt
daar wél uit de URL, want er is geen ingelogde gebruiker. `HandleInertiaRequests`
zet de app-props voor `enroll.*` leeg, net als voor de kaart.

Een inschrijving is een **aparte tabel** (`enrollments`), geen speler. Pas als
de eigenaar goedkeurt (`Actions/Enrollments/ApproveEnrollment`) ontstaan in één
transactie de speler, het ouderaccount (of de koppeling aan een bestaand account
van dezelfde school) en het abonnement. Zo komt er nooit ongevraagd iemand in
het ledenbestand. Hoort het e-mailadres van de ouder bij een andere school, dan
stopt de goedkeuring: een account hoort bij precies één school.

Wat concurrenten (ClubCollect, VreugdOnline, Waresport) allemaal hebben en wij
nu ook: online inschrijven met tariefkeuze, een realtime beeld van ontvangen en
openstaand, een lijst met wie nog moet betalen inclusief contactgegevens, en
exports voor de boekhouder. Wat zij extra doen en bij ons bij Mollie hoort:
automatische herinneringen bij mislukte incasso's.

### Overzichten exporteren

`Support/Exports`: een `Export`-interface, `ExportRegistry` en `ExportWriter`.
**Een nieuw overzicht is één klasse plus een regel in het register**; scherm en
writer zijn generiek. Het betalingsoverzicht komt er zo bij zodra Mollie is
aangesloten.

Meerdere tabbladen? Implementeer `WorkbookExport` en geef `Sheet`s terug; CSV
krijgt dan alleen het hoofdtabblad. `FinancialExport` is het voorbeeld: Overzicht
per maand, Betalingen, Openstaand (met wie je moet bellen) en Abonnementen.

CSV gaat met **puntkomma en BOM**, anders propt Nederlands Excel alles in één
kolom. Geld in exports: pas in `rows()` van centen naar een getal in euro's,
nooit als tekst met euroteken — anders kan Excel er niet mee rekenen.

### Datavisualisatie

- **Eén serie per grafiek.** Zes categorieën in één grafiek wordt spaghetti;
  gebruik kleine grafieken naast elkaar, elk met een eigen titel. Dan is kleur
  nooit de enige drager van betekenis en hoeft er geen legenda bij.
- **Grafiekkleuren staan in `--chart-*` en zijn per thema gecontroleerd** op
  contrast met het vlak eronder (minimaal 3:1). Pas ze niet los aan; het
  merkgroen `#22E06B` zakt op wit naar 2,5:1 en is daar dus te licht.
- **Vaste schaal 0-100.** Een meebewegende as laat kleine schommelingen als
  grote sprongen lezen.
- **Label alleen het eindpunt**, nooit elk punt. En er is altijd een
  **tabelweergave**: cijfers mogen nooit alleen in een plaatje zitten.

### Trainingen en aanwezigheid (fase 4)

Een training hoort altijd bij **een groep**. Wie er verwacht worden wordt
**niet** vastgelegd: dat zijn de actieve spelers van die groep op het moment
dat je kijkt. Komt er een speler bij de groep, dan staat hij vanzelf op de
lijst van de volgende training.

`Attendance` heeft bewust **twee losse velden**, en die moet je niet
samenvoegen:

| Veld | Wie zet het | Wat betekent het |
|---|---|---|
| `registration` | speler of ouder, vooraf | "ik kom" / "ik kom niet" |
| `status` | trainer, achteraf | aanwezig / afwezig |

Iemand kan zich afmelden en toch komen, of niets zeggen en er gewoon staan.
Juist dat verschil is voor een school interessant, dus het blijft gescheiden.
`RegistrationController` raakt `status` nooit aan, en andersom.

Wekelijks herhalen maakt **losse** trainingen, geen reeks. Er is dus geen
"pas de hele serie aan" — dat is bewust weggelaten tot iemand erom vraagt.

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

### Ontwikkelingsdoelen (Fase 7)

- `goals`: één actief doel per speler per categorie; `start_rating` = kaartcijfer op het moment van stellen, `target_rating` = rapportcijfer × 10. Een nieuw doel in dezelfde categorie annuleert het oude.
- "Op koers" = afgelegde weg (start → nu → streef) ≥ verstreken tijd (start → vandaag → einddatum). Zie `Support\Goals\GoalProgress`. Geen extra begrippen.
- Beoordelen gebeurt alleen in `Actions\Goals\EvaluateGoals`, aangeroepen vanuit `StoreReport` na de kaartberekening: gehaald → `DoelBehaald` naar ouders + speler, badge `doel_gehaald`, mijlpaal in de tijdlijn; einddatum voorbij → `missed`.
- Trainer/eigenaar stelt en stopt (`GoalPolicy`), ouder/speler ziet alleen. Het rapportscherm toont per categorie een chip "doel 80", niets meer.

### Privacy en bewaartermijn (Fase 8)

- `schools.retention_months` is de termijn in maanden; **null betekent "nog niets
  besloten"**, niet "alles mag weg". Zonder termijn signaleert het scherm niets.
- De klok start bij `players.deactivated_at`, gezet in een `saving`-hook op het
  model — niet in een controller, want een speler gaat op meer dan één plek op
  niet-actief en de datum mag niet van de plek afhangen. Staat niet in `$fillable`.
- **Er verwijdert nooit iets zichzelf.** `Support\Privacy\RetentionOverview`
  signaleert alleen; de eigenaar drukt per persoon op de knop. Een verkeerd
  ingestelde termijn merk je anders pas als de rapporten al weg zijn.
- Inzageverzoek: `Support\Exports\PlayerDataExport` zet alles van één speler in
  één werkmap. Die staat bewust **niet** in `ExportRegistry` — dat register is
  voor schoolbrede overzichten die je uit een lijst kiest.
- Alles hier is van de **eigenaar**. Een trainer beslist niet welke gegevens van
  een oud-lid verdwijnen.

## 6. Werkwijze

- **Fase voor fase.** Het bouwplan staat in `bouwplan-keepersplatform-claude-code.md`
  (fase 0 t/m 12); de feature-scope in `FEATURES.md`. Begin een fase pas als de vorige werkt en getest is.
- **Nooit vooruitlopen.** Bouw alleen wat in de huidige fase staat.
- Kleine, werkende stappen. Na elke werkende stap: **git commit**.
- Bij onduidelijkheid of dubbelzinnigheid: **eerst vragen, niet gokken**.
- Na afronding van een fase: samenvatten hoe het getest kan worden, en
  **wachten op akkoord** voor de volgende fase.

### Fase-status

Volledige scope en status per feature: `FEATURES.md`. Volgorde: het bouwplan.

- [x] Fase 0 — Projectopzet & fundament
- [x] Fase 1 — Datamodel & multi-tenancy
- [x] Fase 2 — Rapport → spelerskaart
- [x] Fase 3 — Spelers- & groepsbeheer (later: Gebruikers)
- [x] Fase 4 — Planning & aanwezigheid (+ kalender, trainers per training)
- [x] Fase 5 — Voortgang & ouder-ervaring (+ verzamelkaart)
- [x] Fase 6 — Eigenaar-dashboard (+ exports, online inschrijven)
- [x] Fase 7 — Ontwikkelingsdoelen (doel per categorie, op koers, badge, tijdlijn)
- [x] Fase 8 — Bewaartermijn, inzage en verwijderen (AVG); ondertekenen geschrapt
- [ ] Fase 9 — Betalingen (Mollie): schermen en model staan, koppeling niet
- [ ] Fase 10 — Communicatie
- [ ] Fase 11 — White-label & subdomein
- [ ] Fase 12 — Productie & lancering (PWA, deploy)

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
