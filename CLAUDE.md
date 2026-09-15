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
`AppTopbar.vue`.** `NavigationTest` loopt per rol elk getoond item echt af.

#### De balk bovenin

Het menu staat in een **donkere balk bovenaan** (`AppTopbar`), met de lichte
werkvloer eronder. Dat scheidt "waar ben ik in de app" van "waar werk ik aan",
en het is dezelfde donkere kant van het merk als de spelerskaart — geen derde
kleurwereld.

De items zitten in **groepen met een uitklap**: Agenda, Klanten, Financiën,
Mijn bedrijf. Twee regels houden dat eerlijk, allebei in `MainNavigation`:

- **Een groep zonder zichtbare items verdwijnt.** Een lege uitklap laat je
  zoeken naar iets wat er niet is.
- **Een groep met één zichtbaar item wórdt dat item.** "Financiën" met alleen
  Betalingen erin is een woord dat iets anders belooft dan het doet.

Een test die wil weten óf iets in het menu staat moet dus een niveau dieper
kijken; daar is `TestCase::navHrefs()` voor.

Een verlopen sessie (419) wordt in `bootstrap/app.php` afgevangen met een
melding in plaats van een foutpagina.

#### Klanten, Personeel en Mijn bedrijf

Het menu-item **Gebruikers** heette naar de tabel, niet naar de werkelijkheid.
Het is nu **Klanten** (`/clients`): **één lijst** met de speler als regel en
zijn ouder(s) uitklapbaar eronder. Twee tabbladen naast elkaar gingen ervan uit
dat je een ouder los zoekt; een school denkt in een kind met iemand erbij die
je belt. Zoeken kijkt daarom ook naar de naam en het e-mailadres van de ouder.

Dat is **alleen een samenvoeging in de weergave**: een speler blijft een
profiel in `players` en een ouder een account in `users`. `/clients/guardians`
stuurt door naar het overzicht.

Naast "niet actief" staat er één betaalstatus bij een speler: **betaling
openstaand**, en dat is alleen een rekening die de vervaldatum voorbij is. Zou
elke openstaande termijn meetellen, dan kleurt de hele lijst oranje en zegt de
melding niets meer. Staat de betaallaag uit, dan wordt hij niet berekend.

**Trainers staan niet bij de klanten**, maar onder Mijn bedrijf → Personeel
(`/staff`). Een trainer is geen klant, en hem tussen de spelers zetten maakt
beide lijsten onbruikbaar.

`/users` en `/players` sturen door naar `/clients`; die adressen stonden in
bladwijzers voordat dit Klanten heette.

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

Vijf rollen via spatie/laravel-permission. Vier horen bij een school; de
vijfde staat er juist boven:

- `platformbeheerder` — beheert het platform, heeft **geen** `school_id`


- `eigenaar` — beheert de school, ziet alles binnen de eigen school
- `trainer` — vult rapporten in, ziet **alleen zijn eigen groepen en spelers** (zie "Het trainer-account")
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
| Primair / actie | Groen, diep genoeg om wit op te lezen | `#12813D` |
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
| Basis / achtergrond | Neutraal bijna-zwart, vleugje blauw | `#0D0F12` |
| De balk bovenin (`--topbar`) | Donkerblauw, zoals hij altijd was | `#111A2E` |
| Surface (kaarten, panelen) | Neutraal donkergrijs | `#171A1D` |
| Surface hoog / randen | Iets lichter grijs | `#25282D` |
| Primair / actie | Fel groen | `#22E06B` |
| Accent (kaart, badges, mijlpalen) | Goud/metallic | `#D4AF37` |
| Tekst primair | Bijna-wit | `#F1F5F9` |
| Tekst secundair | Grijsblauw | `#99A3B3` |

Op donker komt diepte van kleurverschil, niet van harde schaduwen.

**Eén basis voor de donkere panelen.** `--background` in `.theme-donker` is
de donkere basiskleur, bewust **neutraal** en niet blauw, zodat de kaart het
enige is dat kleur heeft. De balk bovenin heeft als enige een eigen tint,
`--topbar`, op verzoek van de eigenaar precies zoals hij altijd was. Wil je
een donker vlak, dan is het `theme-donker` + tokens en verder niets.

**Ook de speler werkt op de lichte werkvloer.** Een volledig donker
speler-account was te zwart; alleen de spelerskaart staat in een donker
paneel (kaartpagina én spelerdashboard, hetzelfde paneel met "Hoe werkt mijn
rating?" eronder). De donkere balk bovenin is voor iedereen dezelfde.

**De gloed achter de kaart** komt van `components/CardGlow.vue`: een zachte
radiale gloed in de levelkleur (koper, chroom, goud, holografisch; staalgrijs
zonder rapport) als laag ónder de kaart. Zo straalt de kaart en hangt hij aan
de pagina vast in plaats van erop te zweven. Hij staat op alle drie de plekken
waar de kaart staat, en de kaart zelf verandert er niet door.

### Gedeelde schermen

Inloggen, registreren en de startpagina zijn de gedeelde voordeur en staan op
**donker** — dat is de merkbeleving die je als eerste ziet.

De **openbare pagina's voor ouders zijn licht**: de aanmeldpagina
(`/inschrijven/{slug}`) en de betaalpagina uit een e-mail. Die staan vaak in een
iframe op de eigen website van de school, en een donker vlak in een lichte
website valt uit de toon. Ze gebruiken dezelfde tokens, dus het is één klasse
verschil — geen aparte kleuren.

### Het logo

Het merk staat als bestand in `public/brand/`: het merkteken (`mark-64.png`,
`mark-256.png`) en het volledige logo in twee varianten — `logo.png` met
donkere letters voor een lichte achtergrond, `logo-donker.png` met witte voor
een donkere.

- **`AppLogoIcon`** is het merkteken, **`AppWordmark`** het hele logo met de
  naam. De tegel hoort bij het teken: het staat op een beginscherm, in een
  tabblad en in een lichte balk, en zonder eigen achtergrond verdwijnt het daar.
  Zet er dus geen gekleurd vakje omheen.
- **De app-iconen en de favicon worden geschaald uit `mark-256.png`**
  (`php artisan playerpath:icons`), niet nagetekend. Zo staat op het beginscherm
  van een ouder hetzelfde logo als in de app.
- Het merkgroen van PlayerPath staat als `merk` in de Tailwind-config, los van
  `--primary`: die mag een school met haar eigen kleur overschrijven, en het
  logo van PlayerPath hoort daar niet in mee te kleuren.
- Heeft een school een eigen logo, dan wint dat in de app en op de inlogpagina
  (`branding.logo`). Het PlayerPath-logo is de terugval.

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
| `Invitation` | school | `name`, `email`, `role`, `token`, `player_ids?`, `expires_at`, `accepted_at?` |
| `AvailabilityRule` | school | `user_id`, `weekday` (1-7), `daypart` — het gewone ritme van een trainer |
| `AvailabilityException` | school | `user_id`, `starts_on`, `ends_on`, `daypart?`, `available`, `note` |

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

Ze staan niet in één lijst: **Klanten** (`/clients`) toont de spelers met hun
ouders eronder, en trainers staan onder Mijn bedrijf → Personeel (`/staff`).
Ouders koppel je op de pagina van een speler, omdat een ouder zonder kind niets
betekent.

Een **training** heeft nul of meer **trainers** (`training_user`). Elke trainer
ziet het hele rooster, zodat invallen en ruilen niet vastloopt — maar de
koppeling is sinds het trainer-account óók de bron van "zijn groepen en
spelers" (`TrainerScope`). Wie invalt wordt aan die training gekoppeld en hoort
er vanaf dat moment bij.

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

- **verplaatst de app geen cent en doet niets alsof.** Een abonnement brengt
  wel een *openstaande* rekening voort — anders weet een school die per
  overboeking int niet wie er nog moet betalen — maar die staat op `open`,
  zonder betaaldatum en zonder kenmerk bij een provider. Een betaling op
  `paid` zetten die nooit binnenkwam gebeurt nooit.
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

### Het dashboard is opgebouwd uit widgets

Een dashboard beantwoordt twee vragen, in deze volgorde: **"hoe gaat het?"** en
**"wat moet ik doen?"**. Vandaar de volgorde op het scherm: snelle acties, het
aandacht-blok, de kerncijfers, de verdieping, en onderaan wat aardig is om te
weten.

- **`App\Enums\DashboardWidget` is de enige lijst.** Een widget erbij is één
  case plus één Vue-component. **Hernoem een waarde nooit** zonder migratie: hij
  staat in `users.dashboard_layout`.
- **De standaardindeling staat in code** (`WidgetRegistry::defaultLayout()`),
  niet in de database. Wie niets heeft ingesteld krijgt hem, en een widget die
  er later bijkomt verschijnt dan vanzelf. Zou de standaard bij het aanmaken
  van een account worden weggeschreven, dan zag een bestaande school nieuwe
  widgets nooit.
- **Wat je niet mag zien, bestaat niet.** De registry filtert op rol en op de
  functies van de school, dus zo'n widget kan niet in een opgeslagen indeling
  opduiken en het scherm berekent er ook niets voor.
- **Er wordt alleen berekend wat er staat.** Een weggehaalde widget kost geen
  enkele query — dat is een keuze in de weergave, geen kwestie van iets
  verbergen dat toch al opgehaald is.
- **Per gebruiker.** Een trainer kijkt naar zijn rapporten en de eigenaar naar
  zijn omzet; die twee op één indeling zetten betekent dat er altijd één van de
  twee ontevreden is.

#### De bewerkmodus

`DashboardGrid` gebruikt **grid-layout-plus** (de onderhouden Vue 3-opvolger van
vue-grid-layout) voor het raster en het slepen. Zelf schrijven zou betekenen dat
slepen met een vinger een eigen project wordt.

- **Opslaan gaat altijd via de server** (`PATCH /dashboard/indeling`). Een
  indeling die alleen in de browser bestaat staat op je telefoon anders dan op
  je laptop, en dat is niet wat "mijn indeling" hoort te betekenen.
- **De server vertrouwt niets.** Een widget die deze rol niet mag zien valt
  eruit, een dubbele sleutel wordt er één, en een breedte die niet bestaat valt
  terug op de standaard. Het formulier omzeilen levert dus nooit een dashboard
  op met cijfers waar iemand niet bij mag.
- **Herstellen maakt leeg, het schrijft de standaard niet weg.** Anders zou een
  widget die er later bijkomt bij die gebruiker nooit verschijnen.
- **Niets opgeslagen is de standaard; een opgeslagen lege lijst is een leeg
  dashboard.** Wie alles weghaalt hoort niet stiekem de standaard terug te
  krijgen.
- **Op een telefoon gaat het alleen over volgorde.** Een raster van twaalf
  kolommen op 375 pixels is geen raster. Wat je daar versleept verandert dus
  alleen de volgorde; de breedtes van je grote scherm blijven staan, anders is
  je laptopindeling weg zodra je hem op je telefoon aanraakt.
- **Bewerken toont een dekkende laag met alleen de naam.** Half doorzichtig was
  onleesbaar: de knoppen kwamen bovenop de tekst van de widget zelf. In die
  modus gaat het over waar iets staat, niet over wat erin staat.

**Het aandacht-blok is geen widget.** Het staat vast bovenaan en is niet te
verplaatsen: het is het antwoord op "wat moet ik doen?". Zit er niets in, dan
staat er één rustige regel — "Alles loopt — niks te doen" — en geen leeg vak met
een kopje: dat leest als een fout, en zwijgen laat je twijfelen of je iets mist.

**Kleur is een signaal, geen versiering** (`Support\Dashboard\Signal`). Daar
staan de drempels, op één plek: groen vanaf 75%, oranje vanaf 50%, rood
daaronder, en grijs als er nog niets te zeggen valt. Een trend kleurt op de
richting die goed ís — bij openstaande rekeningen is omhoog juist slecht. De
server stuurt per cijfer een `tone` mee; de Vue-kant vertaalt die naar een
klasse (`lib/tone.ts`) en verzint geen eigen grenzen. Toen elk vak zijn eigen
drempel had was bijna alles oranje, en dan zegt kleur niets meer.

**Wegklikken kan wel, maar betekent "gezien".** Er wordt een vingerafdruk van de
inhoud opgeslagen (`users.attention_dismissed`), niet "verborgen". Verandert er
iets — een mislukte betaling erbij, een speler die stilvalt — dan komt het blok
terug. "Voorgoed weg" zou betekenen dat een school een half jaar later niet weet
dat er zeven rekeningen openstaan omdat iemand ooit op een kruisje drukte. En
een weggeklikt blok verdwijnt hélemaal: "Alles loopt" tonen terwijl er signalen
zijn zou een leugen zijn.

**Elk cijfer staat op precies één plek.** Omzet is een kerncijfer bovenaan en
staat dus níét ook in het financiële vak — maar haalt een eigenaar die tegel
weg, dan verschijnt hij daar juist wél, want anders ziet hij zijn omzet nergens.
Dat besluit valt op de server, want die weet welke widgets er staan.
"Verwacht op jaarbasis" is geschrapt: dat is lopende abonnementen maal twaalf,
en daar kan niemand iets mee. Verder geldt: "spelers zonder rapport" staat in het
aandacht-blok en niet meer als los blok; "groepen" is als kerncijfer geschrapt
omdat het nooit verandert.

#### Op een telefoon staat er minder

Een eigenaar pakt zijn telefoon om in tien seconden te zien hoe het ervoor
staat en of er iets moet gebeuren; de analyse doet hij op zijn laptop. Het
mobiele dashboard toont dus alleen wat op dat moment telt, en past binnen twee
schermlengtes.

- **`DashboardWidget::onMobile()` is de enige lijst**, net als `ownerOnly()` en
  `feature()`. Er is bewust geen aparte mobiele indeling: dat zou een tweede
  plek zijn waar een nieuwe widget vergeten kan worden. Wat op `false` staat
  verdwijnt niet — het staat op het grote scherm.
- **De volgorde verschilt, de inhoud niet.** Op een laptop staan de snelle
  acties bovenaan (je ziet het hele scherm toch, en je opent het om iets te
  doen); op een telefoon staat het aandacht-blok daarboven, want daar zie je
  maar één ding tegelijk en is "moet er iets gebeuren?" de eerste vraag. Dat is
  één flexkolom met order-klassen, geen tweede dashboard.
- **Wat eraf gaat laat een spoor achter** (`MobileSummary`): het
  ontwikkelingsvak wordt één regel met de dekking van de rapporten, het
  financiële vak één regel met wat er openstaat. Een wegwijzer, geen
  samenvatting — en een regel zonder inhoud verdwijnt.
- **Hooguit twee snelle acties**; de rest zit onder de plusknop in de balk, waar
  hij toch al stond. Vier knoppen duwen de cijfers een half scherm omlaag.
- **`mobileCompact()` zegt wat er naast elkaar past.** Twee kerncijfers naast
  elkaar; alles met een lijst erin over de volle breedte, want de helft van 375
  pixels kapt elke naam af.
- **Verjaardagen staan er niet op.** Aardig om te weten is geen reden om op je
  telefoon te scrollen.

**De bewerkmodus is op een telefoon een lijst, geen raster.** Twaalf kolommen op
375 pixels zijn geen kolommen: daar gaat het over volgorde en over aan of uit,
met pijltjes in plaats van slepen — slepen in een lijst waarin je tegelijk wilt
scrollen is een gok, een pijl van 44 pixels niet. In die lijst staat álles, ook
wat op een telefoon niet getoond wordt, met "alleen groot scherm" erbij; anders
kun je iets dat je daar niet ziet ook nergens weghalen. De breedtes van het
grote scherm blijven staan.

**Buiten de bewerkmodus staat "Indeling aanpassen" op een telefoon onderaan.**
Het is iets wat je één keer doet, en de bovenste regel van dat scherm is de
duurste plek die er is.

#### Groei komt uit de rapporten, niet uit de kaart

`players.overall_rating` is een momentopname zonder historie: het zegt hoe een
speler er nú voor staat, niet waar hij vandaan komt. `DashboardTrends` en
`DevelopmentOverview` rekenen daarom met `reports` + `report_scores`, met
dezelfde omrekening als de kaart (gemiddelde × 10), zodat een stijging van "5"
overal hetzelfde betekent.

Een speler telt alleen als stijger of daler bij **twee** rapporten binnen de
periode. Met één rapport valt er niets te vergelijken, en een ouder rapport van
maanden terug erbij halen zou "groei deze maand" iets anders laten betekenen
dan het zegt.

**Verjaardagen** (`SchoolDashboard::birthdays()`) gaan op **dag en maand**, niet
op datum: het jaar in `date_of_birth` is het geboortejaar. De leeftijd die
erbij staat is de leeftijd die het kind *wordt* — dat is wat je in een berichtje
zet. Rond de jaarwisseling loopt het venster over 31 december heen; daar staat
een test op.

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

### Onboarding: van niets naar een draaiende school

Een lege omgeving is de vijand. Wie voor het eerst inlogt en overal "nog niets"
ziet staan, weet niet wat het product doet en gaat het ook niet uitzoeken. De
onboarding bestaat daarom uit vier dingen die elkaar aanvullen, in deze
volgorde: er staat meteen iets dat werkt (voorbeelddata), een rondleiding van
veertien stappen door de echte app, dán de wizard waarin je je eigen school
inricht, en daarna een lijst met wat er nog moet.

`schools.onboarding` houdt bij hoe ver een school is (`Support\Onboarding\
OnboardingState`). Zelfde afspraak als bij de functies en de rekenkern:
standaarden in code, in de database alleen wat deze school daadwerkelijk deed.
Wat je aan de database kunt vragen staat er níét in — of er spelers zijn, of er
een rapport is — want dan heb je twee waarheden die uit elkaar lopen zodra
iemand zijn enige speler verwijdert. Wél staat erin waar iemand gebleven was:
`tour_step` en `wizard_step`, zodat wie halverwege wegloopt terugkomt waar hij
was en niet opnieuw bij stap één.

#### Voorbeelddata

`Actions\Onboarding\SeedDemoData` zet bij het aanmaken van een school vier
spelers met elk drie rapporten neer, plus een groep, drie trainingen (één met los inschrijven), een
aanbod, een locatie en een bericht. `RemoveDemoData` haalt het er in één keer
weer uit. Vier regels:

- **Het zijn echte rijen in de echte tabellen**, met `is_demo` erop. Geen
  demo-modus die elk scherm moet samenvoegen: de kaart wordt echt doorgerekend
  en de agenda is echt de agenda, dus wat je ziet is wat het product doet.
- **`is_demo` staat in geen enkel `$fillable`.** De seeder schrijft via
  `forceFill`; zou de vlag mass-assignable zijn, dan kan een formulier een
  echte speler als voorbeeld markeren en verdwijnt die bij het opruimen.
- **Overal staat het label "voorbeeld"** (`DemoBadge`) en op elke pagina staat
  de balk met de opruimknop (`DemoBanner`, alleen voor de eigenaar).
  Voorbeelddata die je niet herkent en niet kunt weghalen is erger dan een leeg
  scherm — dan staat er over een half jaar een verzonnen kind in een echt
  ledenbestand.
- **De rapporten lopen op in de tijd** (acht, vier en één week terug), zodat er
  groei te zien is. Vier identieke rapporten geven een vlakke lijn, en dan lijkt
  het product stuk. Er komt bewust **geen foto** bij: een gezicht van een kind
  erbij verzinnen doe je niet in een product over kinderen.

**De startchecklist telt voorbeelddata niet mee** (`Player::real()` en de andere
`real()`-scopes). Anders is je school "af" zonder dat je één echte speler hebt
toegevoegd, en heeft de lijst niets gezegd.

**De voorbeelddata gaat vanzelf weg zodra de wizard is afgerond.** Op dat
moment heeft de school haar eigen naam, groepen en aanbod, en dan staat een
verzonnen kind alleen nog in de weg. De opruimknop blijft bestaan voor wie
eerder wil.

#### De wizard: "Je school" vooraan, groepen en trainers achteraan

De intake — naam, adres van de inschrijfpagina (slug), contactgegevens, logo,
merkkleur, eerste locatie — is **stap 1 van de bestaande wizard** Inschrijven
en betalen geworden, niet een tweede wizard ernaast. De andere gevraagde
onderwerpen (aanbod, innen, kortingen, wachtlijst, toestemmingen) stonden daar
al; een aparte intake zou betekenen dat er twee plekken zijn waar je hetzelfde
instelt. Achteraan staan **Groepen** (namen met leeftijdscategorie; wat er al
is blijft staan) en **Trainers** (het gewone uitnodigingsformulier).
`EnrollmentSettings::STAPPEN` telt daardoor **tien** stappen; stap 1, 8 (de spelerskaart, zie hieronder), 9 en 10
slaan niets in die klasse op, die schrijven naar de school, de kaartinstelling, de groepen en de
uitnodigingen zelf.

- **Stap 1 laat zien wat je kiest**: het logo en de kleur staan meteen in een
  nagebouwde balk met een knop. Opslaan en terugkomen om te kijken of het goed
  staat is precies waar iemand afhaakt. De slug volgt de naam totdat je hem
  zelf aanpast.
- **Elke stap loopt via `volgende()`**, dat `wizard_step` bijhoudt; het
  menu-item opent dan bij die stap. De laatste stap rondt af, ruimt de
  voorbeelddata op en stuurt naar het dashboard, waar de checklist wacht.
- **Overslaan mag, bij elke stap** (`enrollment-settings.skip`): elke vraag heeft
  een bruikbare standaard, en wie er nu geen antwoord op heeft moet verder kunnen
  in plaats van te stoppen. Bij de laatste stap telt overslaan als afronden.

#### De startchecklist

**De lijst komt pas na de rondleiding** (`tour_seen_at`). Op het eerste scherm
stonden anders de voorbeelddata-balk, de rondleiding, het aandacht-blok én de
lijst tegelijk om aandacht te vragen, en wie nieuw is weet dan niet waar hij
moet beginnen. De rondleiding eindigt bij de wizard; daarna is de lijst
precies wat er nog te doen is. Om dezelfde reden onthoudt de rondleiding haar
stap **per school** in de browser: wie op dezelfde laptop als tweede school
inlogt begint bij één, niet bij de stap van de vorige.

Acht stappen (`Support\Dashboard\SetupChecklist`), in de volgorde van het
fundament: **schoolgegevens → locatie → groep**, en dan pas eerste speler,
eerste training, eerste rapport, ouder uitnodigen, eerste aanbod — elk met
een knop naar de plek waar je hem afmaakt. Die volgorde is er omdat het
andersom vastloopt: een training zonder groep kan niet, en een speler zonder
groep staat nergens op een lijst. De formulieren zeggen dat ook: het
spelerformulier waarschuwt bovenaan als er nog geen groep is (met de link),
het trainingsformulier blokkeert zonder groep en waarschuwt zonder locatie.
`OnboardingProgress` in het platformbeheer telt dezelfde acht. Drie regels:

- **Het eerste rapport is gemarkeerd** (`highlight`). Dat is het moment waarop
  een lege kaart een spelerskaart wordt en een ouder voor het eerst iets ziet;
  de rest is administratie eromheen.
- **Af is af.** Eén felicitatie, dan `checklist_completed_at` en voorgoed weg.
  Het scherm meldt dat zelf: zou de server het bij het laatste vinkje wegzetten,
  dan zag niemand ooit dat hij klaar was.
- **Wegklikken mag en is terug te halen.** Anders is de enige uitweg alle vijf
  stappen doen, ook die je niet wilt.

#### De rondleiding

Zestien stappen door de echte app, alleen voor de eigenaar, vóór de wizard.
**Eerst een welkom in het midden van het scherm** (`AppTour.vue`): welkom
met de schoolnaam, de vier stappen (er staat al iets, de rondleiding,
inrichten, de startlijst) en een knop die het kaartje rechtsonder aanwijst.
Een kaartje dat meteen rechtsonder verscheen viel niet op tussen alles wat
er verder op het scherm stond. Twee stappen laten zien **hoe ouders
inschrijven**: de echte inschrijfpagina in een kader
(`/onboarding/aanmeldpagina`) en de trainingen zoals een ouder ze ziet, met
een open voorbeeldtraining onder "Inschrijven"
(`/onboarding/ouderweergave/trainingen`; `SeedDemoData::ensureOpenTraining`
zet die alsnog neer bij een school die er nog geen had). In beide staat dat
een ouder kiest tussen online betalen en contant bij de school.
De stappen komen **van de server** (`Support\Onboarding\OnboardingTour`,
gedeeld als `onboarding.tourSteps`): elke stap heeft een adres en een anker
(`data-tour="…"`), en de rondleiding navigeert er zelf naartoe. Op de server,
omdat "een gevulde spelerskaart" het adres van een echte voorbeeldspeler is,
en omdat de functies van de school bepalen welke stappen er zijn — staat
Betalingen uit, dan is er geen stap Financiën.

- **Het scherm blijft te gebruiken.** Er ligt geen donkere laag overheen: je
  kunt scrollen, klikken en rondkijken terwijl het kaartje staat. De eerste
  versie blokkeerde alles, en dan is een rondleiding een diavoorstelling over
  een scherm dat je niet mag aanraken. Het kaartje staat rechtsonder (op een
  telefoon onderaan) en klapt met één tik in tot een smal balkje.
- **Elke stap zegt wat je ziet, wat je ermee doet, en wat je zelf kunt
  proberen** (`tip`). Geen vaktaal: er staat "punten" en niet "XP". De
  pijltjestoetsen bladeren, maar niet zolang iemand in een invulveld typt.
- **Eén ring om het onderdeel waar het over gaat.** Is het anker groter dan
  zeventig procent van het scherm (het hele dashboard), dan komt er geen ring:
  een ring om alles wijst nergens naar.
- **"Wat een ouder ziet" is een echt scherm**: `/onboarding/ouderweergave`
  toont het gezinsdashboard met de voorbeeldspelers als kinderen. Een plaatje
  ervan zou verouderen; dit is het scherm zelf.
- **Waar je was staat op de server** (`tour_step`), zodat een eigenaar die
  halverwege zijn telefoon wegstopt op zijn laptop verdergaat. Loop je tijdens
  de tour zelf naar een andere pagina, dan staat er een klein kaartje om terug
  te gaan naar de stap. In de browser houdt `sessionStorage` de stap ook vast,
  omdat de opslag naar de server anders een race is met de navigatie.
- **Het anker wordt een paar keer opnieuw gezocht** (zes keer, om de 300 ms):
  na een navigatie is de pagina er eerder dan de component erop.
- **Altijd overslaan**, en opnieuw te starten met de vraagtekenknop in de
  balk. Het einde van de tour is het begin van de wizard: "Mijn school
  inrichten" opent stap 1.

Het platformbeheer (`Support\Platform\OnboardingProgress`) laat per school
zien waar ze staan: welke stap van de rondleiding, welke stap van de wizard,
en welke punten van de checklist.

#### Uitnodigen

`invitations` met een token van 64 tekens, een eigen geldigheidsduur
(`schools.invitation_valid_days`, standaard veertien dagen) en de kinderen die
bij activatie gekoppeld worden. Dat ging via de wachtwoord-vergeten-route, en
die kan die twee dingen niet.

- **Het gewone geval is één persoon**: naam, e-mailadres, versturen. Bulk is
  daarnaast geen luxe — een school die overstapt heeft honderd ouders — maar
  zit achter "Meer opties": het formulier neemt daar regels aan van de vorm
  `Naam <mail@x.nl>`, `Naam, mail@x.nl` of een kaal adres, wat mensen tóch al
  plakken. De relatie (moeder, vader) staat er ook achter.
- **Een account ontstaat pas bij activatie.** Tot die tijd is er alleen een
  uitnodiging; anders staat er een half ledenbestand met mensen die nooit hebben
  ingelogd, en telt de school ze wel mee.
- **Elke weg om iemand toe te voegen is een uitnodiging**
  (`Actions\Onboarding\SendInvitation`): het formulier op Personeel en bij
  het kind, de oude routes `guardians.invite` en `trainers.store`, en in
  platformbeheer een eigenaar bij een nieuwe school of een account voor een
  school. Die laatste maakten eerst meteen een account en stuurden "Kies een
  nieuw wachtwoord": dat leest als een fout voor iemand die nog nooit een
  wachtwoord had, en die reset-link verloopt na een uur. De mail heet nu
  "Welkom bij …" en heeft een eigen tekst voor een eigenaar. De knop
  "wachtwoord resetten" in platformbeheer blijft wel een reset-mail: daar
  vraag je er ook om.
- **Aan een eigenaar schrijft PlayerPath, niet de school** (`Uitnodiging`,
  en de wachtwoordmail in `FortifyServiceProvider`): ons logo, afzender
  PlayerPath, geen antwoordadres van de school, ondertekend door
  `MailBrand::PLATFORM_AFZENDER` ("Michael van PlayerPath") en met ons
  telefoonnummer. Een eigenaar is klant van ons; zijn school bestaat net,
  en "vraag Keepersschool Rob om een nieuwe uitnodiging" is voor de
  eigenaar van Keepersschool Rob een doodlopende weg. Ouders en trainers
  houden de mail van de school.
- **Opnieuw uitnodigen** (platformbeheer → Gebruikers, `SchoolUserController::reinvite`):
  alleen bij een account dat nooit is geactiveerd (`email_verified_at` leeg).
  Een uitnodiging mag niet naar een adres met een account, dus zo'n account
  (vaak nog uit de tijd van de reset-link) wordt weggehaald, met zijn
  reset-link en meldingen, en vervangen door een uitnodiging met de
  welkomstmail. Een ouder houdt zijn kinderen in de uitnodiging. Een account
  dat in gebruik is haal je hier nooit weg.
- **Een wachtwoordlink is twee dagen geldig** (`auth.passwords.users.expire`,
  standaard 2880 minuten, `AUTH_PASSWORD_RESET_TOKEN_EXPIRE`), en eenmalig.
  Een uur was te kort voor iemand die door platformbeheer een link kreeg en
  de mail pas 's avonds opent. De mail is neutraal ("Kies je wachtwoord"),
  zonder "je hebt gevraagd": hij gaat ook naar wie niet zelf op wachtwoord
  vergeten drukte.
- **Opnieuw versturen maakt een nieuw token**, en de oude link is dan dood.
- **De mail komt van de school**, met haar logo en naam (`mail.uitnodiging`).
  Een ouder heeft zijn kind bij Keepersschool Rob aangemeld en kent ons niet.
- **Ouders worden uitgenodigd vanaf de pagina van het kind**, trainers vanaf
  Personeel. Zo hangt er altijd een kind aan een ouderuitnodiging, en hoeft de
  school na activatie niet alsnog met de hand te koppelen.

#### Ouder en speler krijgen geen wizard en geen rondleiding

Die moeten het meteen snappen; een tour is het verkeerde antwoord op een scherm
dat niet duidelijk is. Wel **één vriendelijke regel bij het eerste bezoek**
(`WelcomeNote`, onthouden in `users.intro_seen_at` — per gebruiker, want het
gaat erover of déze persoon het al zag).

De regel "leeg is weg" blijft gelden voor losse blokken, met één uitzondering:
**is het hele gezinsdashboard leeg, dan staat er één regel die zegt wat er
komt.** Dat is precies de eerste keer dat een ouder inlogt, en een scherm zonder
iets is geen scherm. Bij de speler staat onder een kaart zonder cijfers dat zijn
kaart na de eerste training verschijnt.

#### Voortgang in het platformbeheer

`Support\Platform\OnboardingProgress` rekent met dezelfde acht stappen, en dus
ook met dezelfde regel dat voorbeelddata niet meetelt — anders staat elke verse
school op zeven van zeven en zie je nooit wie er hulp nodig heeft. In de
schoollijst een balkje per school, op de detailpagina de stappen plus wat de
school heeft weggeklikt of gezien. Let op: dat draait in de platformmodus, waar
de scope openstaat, dus elke query begrenst zichzelf expliciet op `school_id`.

### Voortgang, meldingen en de kaart (fase 5)

**Voortgang** (`Support/PlayerCard/PlayerProgress`) toont het cijfer van elk
**rapport afzonderlijk**, niet het doorgerekende kaartgemiddelde. Anders zie je
een afgevlakte lijn in plaats van wat de trainer die dag opschreef. Vanaf twee
rapporten is er een grafiek; daarvoor niet.

**Mijlpalen zijn instelbaar** (`Support\PlayerCard\BadgeSettings`, opgeslagen in
`schools.rating_settings['badges']`): vijftien bestaan er (zes horen alleen bij de inzetkaart), standaard staan er bij de prestatiekaart
**vier** aan (eerste rapport, vijf keer aanwezig, vijf punten groei, doel
gehaald). De eigenaar of trainer kiest op `/mijlpalen` welke gelden, voor alle
spelers of per leeftijdscategorie; `PlayerBadges::for()` geeft alleen die.
Een lijst van negen was te lang, en een kaart met acht "nog te behalen" leest
als een verlanglijst. De tijdlijn op de voortgangspagina toont standaard de
laatste vier momenten, de rest achter "Meer weergeven".

**Eigen mijlpalen** typt de school zelf (naam en omschrijving, ook in
`rating_settings['badges']['custom']`). Die zijn nergens uit af te leiden, dus
een trainer kent ze **met de hand toe** op de pagina van de speler
(`player_badges`, `PlayerBadgeController`; de poort is `createReport`). Elke
eigen mijlpaal krijgt bij het opslaan een vaste sleutel `eigen_…` die bij
hernoemen blijft, anders raakt een kind zijn badge kwijt als de school een
tikfout verbetert. Ze staan op de kaart, het spelerdashboard en in de tijdlijn
naast de afgeleide mijlpalen; de standaardmijlpalen zijn niet met de hand te
zetten.

**Tijdlijn en mijlpalen worden afgeleid**, niet opgeslagen. Er is geen
gebeurtenissen-tabel die uit de pas kan lopen met de werkelijkheid, en een
nieuwe badge kost één regel in `PlayerBadges`. Ook de **level-momenten** komen
er zo bij: `PlayerTimeline` telt de XP-boekingen op volgorde op en kijkt wanneer
de som een drempel passeerde.

#### Het voortgangsscherm

Het scherm beantwoordt in deze volgorde: wat is er gebeurd, waar gaat het over,
en wat nu?

- **De kop is een zin, geen getal**: "+12 gegroeid in drie maanden". Groei wordt
  gevierd, maar een mindere periode wordt niet weggepoetst — een pagina die
  altijd juicht gelooft een ouder na twee keer niet meer. Wel warm: achteruitgang
  hoort bij leren en dat mag er staan.
- **`PlayerProgress::trend()` zet een verschil om in één woord** (sterk gegroeid
  / gegroeid / stabiel / aandacht). De grens ligt op twee punten; minder is ruis.
  Eén plek, zodat de categoriekaarten en de overall-lijn dezelfde taal spreken.
- **`Support\Progress\NextStep` geeft één ding om aan te werken.** Een lopend
  doel wint, anders de laagste categorie plus vijf punten. Bewust één: een
  lijstje met zes verbeterpunten leest als kritiek en niemand begint eraan.
  Zonder cijfers geen voorstel — "werk aan je communicatie" zonder dat er ooit
  iemand naar gekeken heeft is een oordeel uit het niets.
- **De leeftijdscontext staat er positief bij** ("goed voor Onder 14"), met
  dezelfde uitleg als op de kaart: `RatingExplanation` wordt hier hergebruikt,
  dus er is één tekst om te onderhouden.
- **Begin en eind staan als getal bij de grafiek.** Een lijn zonder cijfers laat
  je raden.

**Meldingen** gaan via `NieuwRapport` naar de ouders en de speler zelf, in de
app en per e-mail. De notificatie is `ShouldQueue`: het opslaan van een rapport
mag nooit wachten op een mailserver — dat scherm moet in dertig seconden klaar
zijn. De melding gaat er **na** de transactie uit, zodat een mislukte opslag
nooit alsnog een e-mail oplevert. Lokaal draai je `php artisan queue:work`.

**Het dashboard verschilt per rol.** Eigenaar en trainer zien de school; ouder
en speler hun eigen kind. Eén gedeeld dashboard toonde een ouder schoolbrede
cijfers en knoppen die hij niet mocht gebruiken.

**Elke weergave in `Dashboard.vue` heeft een eigen `v-if` op `view`.** De
ouder- en spelerweergave hingen ooit als `v-else` aan de welkomstregel: bij
het eerste bezoek zag een ouder alleen die regel, en bij de eigenaar viel het
gezinsblok als terugval onder zijn widgets. De server stuurt per rol alleen de
props van die rol; de Vue-kant mag daar geen terugval bij verzinnen.

#### Het ouder-dashboard

`Support\Dashboard\FamilyDashboard`. Een ouder komt voor praktische dingen:
wanneer is de training, moet ik betalen of inschrijven, is er nieuws? De volgorde
is dus: begroeting, wat er nú van je gevraagd wordt, de eerstvolgende
trainingen, waar je kunt inschrijven, je kinderen, berichten.

- **De spelerskaart vulde het hele scherm** en beantwoordde geen van die vragen;
  je moest er elke keer omheen scrollen. Hij staat nu als **klein kaartje** per
  kind — foto, voornaam, cijfer, groei deze maand, XP-balk en de levelkleur als
  rand — en opent met één tik helemaal (`/players/{id}/card`). De kaart zelf is
  niet veranderd; alleen zijn plek.
- **Meerdere kinderen is het gewone geval.** Alles noemt bij welk kind het
  hoort: "Sem en Liam" bij een training, "Voor Liam" bij een rekening. Een rij
  tijdstippen zonder naam is bij twee kinderen onbruikbaar.
- **Leeg is weg.** Geen openstaande rekening, geen aanbod, geen berichten: dan
  staat dat blok er niet. Anders sla je elke dag dezelfde lege kaders over.
- **Eén bron voor "wie zijn mijn kinderen"**: `visiblePlayerIds()`, dezelfde als
  de trainingen, de kaart en de betalingen.

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

#### De kind-link: het kind op zijn eigen account, zonder wachtwoord

Een keeper van acht heeft geen e-mailadres en geen wachtwoord, maar het is
wél zijn kaart. Daarom is er naast de publieke deel-link een **tweede link**
(`players.child_token`, `/kind/{token}`, `ChildCardController`): de ouder
maakt hem op de kaartpagina (met een QR-code om van het scherm te scannen)
en opent hem één keer op de tablet van het kind. De link **logt het kind in
op zijn eigen speleraccount** en onthoudt dat op dat apparaat; daarna opent
de app gewoon op zijn dashboard, precies zoals een speler met eigen inlog.
Een eigen kaartpagina buiten de app was er even, en is bewust weggehaald:
het kind hoort hetzelfde te zien als elke speler, niet een tweede versie.

- **Het account ontstaat bij het eerste openen** (`Actions\Players\
  EnsurePlayerAccount`): naam van het kind, rol speler, een adres op
  `kind.playerpath.nl` dat nergens heen gaat, een wachtwoord dat niemand
  kent, en `notification_preferences.mail = false`. `User::wantsEmail` kent
  die hoofdschakelaar: een kind zonder mailbox krijgt nooit mail, in de app
  ziet het zijn meldingen wel. Heeft de speler al een eigen inlog, dan is
  dat het account.
- **De link is de sleutel.** Alleen de eigenaar en de ouders van dit kind
  maken hem (`PlayerPolicy::share`, dezelfde poort als delen). Opnieuw
  maken geeft een nieuw token en de oude link is dood; intrekken ook; een
  speler op niet-actief zetten sluit beide links vanzelf (`Player::booted`).
  Hij verloopt niet: "link verlopen" na drie maanden snapt een kind niet.
- **Wie al ingelogd was wordt gewisseld naar het kind**: een ouder die de
  link test moet niet op zijn eigen dashboard uitkomen en denken dat de
  link niets doet.
- **Op de kaartpagina staat de kind-link in drie stappen**, altijd open:
  stuur de link naar je kind (knop via het deelmenu van de telefoon, of
  kopiëren, of de QR-code), je kind opent hem en kan de kaart daarna altijd
  bekijken, zet de app op het beginscherm. Dat moet je niet hoeven
  uitzoeken.
- **Via de link komt meteen de vraag om de app op het beginscherm te
  zetten** (`flash.kindWelkom` → `InstallPrompt`), ook als die vraag op dat
  apparaat eerder is weggeklikt. Dat is het moment: de tablet van het kind
  ligt in de hand.
- Zelfde regels als de deel-link: `X-Robots-Tag`, throttle, zoeken op token
  met `withoutScope()`. Alles daarna is gewoon de app, met de school uit
  het account en de policies van een speler.

### Twee spelerskaarten: prestatie of inzet

Veel voetbal- en keepersscholen willen bewust geen cijfers geven, omdat
kinderen zich dan met elkaar gaan vergelijken. Daarom zijn er **twee
spelerskaarten** en kiest de school er één (`RatingSettings::cardMode()`,
`schools.rating_settings['card_mode']`):

| | Prestatiekaart (`prestatie`) | Inzetkaart (`inzet`) |
|---|---|---|
| Na de training | rapport met cijfers per categorie | aanwezig, inzet en houding (tikken) |
| Op de kaart | overall + zes categorieën | level groot, XP-balk, punten, trainingen |
| XP uit | aanwezig, rapport, groei | aanwezig, inzetpunten |
| Voortgang | grafieken uit rapporten | begin- en eindniveau per cursus, in kleuren |

- **Kiezen gebeurt in de wizard** (stap 8, `CardVariantChoice.vue`, met beide
  kaarten als echt voorbeeld naast elkaar; niets voorgekozen en niet over te
  slaan: de school kiest zelf, de inzetkaart draagt het label aanbevolen) en later bij Mijn bedrijf → Spelerskaart
  (`CardSettingsController`), met een waarschuwing vóór het wisselen. Wie niets
  koos houdt de prestatiekaart: zo bleven bestaande scholen zoals ze waren.
- **Wisselen verwijdert niets** (`Actions\Schools\ChangeCardMode`). Alle
  XP-boekingen blijven; `RatingSettings::excludedXpSources()` bepaalt welke
  bronnen meetellen en elke speler wordt opnieuw opgeteld. Zo begint bij de
  inzetkaart iedereen gelijk, en is terugwisselen de stand van vroeger.
- **De inzetkaart heeft ook in de gegevens geen cijfers.** De presenter zet
  `overall`, `grade`, `categories`, `recent_reports` en `goal` leeg; wat niet in
  de JSON zit kan ook niet naast een ander kind gelegd worden.
- **Inzet geven** (`/trainings/{id}/inzet`, `EffortController`,
  `Actions\Trainings\RecordEffort`): dezelfde flow als de snelle rapporten, kind
  na kind. Aanwezig loopt via `RecordAttendance` (rittenkaart en XP op één
  plek); afwezig ruimt de inzet van die training op. `effort_ratings` bewaart de
  gekozen trede én de punten van toen. Treden zijn per school instelbaar (drie
  of vier, naam en punten); **er is geen negatieve trede**, niets kiezen is nul
  extra. Opnieuw opslaan vervangt, telt nooit dubbel.
- **Alles beweegt mee op één vraag**, `usesEffort()`: de rapportflow en
  `ReportPrompts` wijzen naar inzet (`EffortRating::doneFor` zegt wie klaar is),
  het menu toont geen Rapporten, de startlijst vraagt om de eerste inzetpunten,
  `ExportRegistry::$alleenBij` wisselt Rapporten voor Inzetpunten en Voortgang
  per cursus, de tijdlijn toont uitblinkers en cursusmomenten, en de uitleg
  ("Hoe werkt mijn kaart?", `CardFaq`) praat kindertaal: iedereen begint gelijk,
  punten gaan nooit omlaag, genoeg punten maakt je kaart mooier.
- **Mijlpalen horen bij een kaart**: `PlayerBadges::ALLEEN_PRESTATIE` en
  `ALLEEN_INZET`, met `BadgeSettings::standardFor()` als standaard per kaart.
- **Voortgang zonder cijfers** (`course_assessments`,
  `Support\Progress\CourseProgress`, `/aanbod/{id}/voortgang`): per cursus of
  blok (alleen aanbod met een begin en eind, `hasPeriod()`) per categorie een
  begin- en eindniveau plus een verslag. De schaal is van de school: drie tot
  vijf niveaus met naam en kleur uit `RatingSettings::KLEURENPALET`. Een niveau
  wordt opgeslagen als positie met het aantal niveaus van toen (`scale`) en
  teruggerekend, zodat een schaal aanpassen oude niveaus niet verschuift. Ouders
  zien alleen de eigen lijn (`CourseProgressBlock.vue`), nooit andere kinderen.
- **Kleuren of cijfers in het rapport** (`grading`) is een keuze binnen de
  prestatiekaart, standaard cijfers, en staat nu ook bij Spelerskaart (niet meer
  bij Seizoen).

### De spelerskaart als verzamelkaart

`components/PlayerCardVisual.vue` is de kaart zelf, gebruikt op de kaartpagina,
het gezinsdashboard en de publieke deel-pagina. De gegevens komen op alle drie
uit `Support\PlayerCard\PlayerCardPresenter` (`for($player, public: true)` voor
de deel-link: voornaam + initiaal, geen school). Eén component en één presenter,
zodat die drie nooit uit elkaar lopen.

- **Het level zie je aan het frame**, niet aan een pilletje: koper (brons),
  chroom (zilver), warme gloed (goud), holografisch en langzaam bewegend
  (elite, uit bij `prefers-reduced-motion`). Per level is dat één CSS-blokje
  met `--pp-metaal`, `--pp-tier` en `--pp-gloed`. Zonder rapport: staalgrijs.
- **De facet-hoeken komen van een `clip-path`**, en die knipt ook de schaduw
  weg. Daarom zit de gloed als `filter: drop-shadow` op de wrap, niet als
  `box-shadow` op het frame.
- **Vaste indeling, identiek voor elk level**: merk bovenaan, foto in de
  bovenste helft met fade, overall linksboven met positie en leeftijdscategorie,
  drie badges rechtsboven (de meest recente; "nieuwe categorie" gaat voor),
  voornaam klein en achternaam groot, zes categorieën **voluit** in twee
  kolommen, XP-balk met "nog X punten tot je volgende upgrade", en onderaan
  seizoen, level en school. Geen afkortingen: een kind van acht hoeft "INZ"
  niet te raden.
- **Zonder foto een silhouet** met "Foto toevoegen" voor wie de speler mag
  bewerken (`photoHref`); nooit een initialen-cirkel als er wél een foto is.
- **"Hoe werkt mijn rating?"** (`RatingExplanation.vue`) hoort bij de kaart en
  is verplicht: rating is relatief aan leeftijdsgenoten, omhoog door trainen en
  groeien, wat de categorieën betekenen, levels en badges, en waarom een rating
  kan dalen. `audience="trainer"` voegt het blok toe over hoe je scoort. Het
  paneel is bewust donker, ook in de lichte admin-schil.
- **Mobiel-first**: maximaal 22.5rem breed, onder 360px wordt het cijfer kleiner.
  Meet `scrollWidth > clientWidth` op labels; badge-labels breken af, ze kappen
  niet af.

De kaart is bewust donker, ook binnen de lichte admin-schil: dat is de
speler/ouder-kant van het merk (hoofdstuk 4).

#### Wat de kaart levend maakt

- **Hij kantelt mee** (`--pp-rx/--pp-ry` op `.pp-frame`): met de muis op een
  laptop, met de gyroscoop op Android. Op iOS niet — dat vraagt een
  toestemmingspop-up, en die is het niet waard. Een lichtval
  (`.pp-frame-licht`) loopt mee. Alles uit bij `prefers-reduced-motion`.
- **Per categorie een pijltje** (`categories[].delta`,
  `CalculatePlayerCard::deltas()`): de kaart van nu tegenover de kaart vóór
  het laatste rapport, met dezelfde demping — dus geen pijl van twintig
  punten door één uitschieter. Zonder vorige stand geen pijl.
- **Omdraaien** ("Draai om"): geen echte 3D-achterkant (die verdraagt zich
  slecht met de clip-path en de gloed) maar hetzelfde frame dat tot 90°
  draait, van inhoud wisselt en terugdraait. Achterop: de laatste drie
  rapporten (`recent_reports`), het lopende doel (`goal`) en de mijlpalen.
  **Publiek zonder trainer, toelichting en doel** — dat is wat een trainer
  over een kind opschreef, niet voor internet.
- **Confetti** (`lib/confetti.ts`) bij een nieuw level en bij de eerste foto.
  Eén keer, anderhalve seconde, zonder bibliotheek; niets bij "minder
  beweging".
- **Tijdens het uitsnijden beweegt de kaart mee**: `PhotoCrop` geeft de
  uitsnede als `preview` door, het kaartje in het venster én de echte kaart
  erachter tonen hem meteen.
- **Een rugnummer** (`players.shirt_number`, 1–99, optioneel) staat groot
  rechtsonder op de foto in het metaal van het level. Het is versiering van
  de eigen kaart: de eigenaar, de ouders en het kind zelf zetten het
  (`PlayerPolicy::personalise`, `PATCH /players/{player}/rugnummer`), de
  trainer niet. En elke kaart draagt een **kaartnummer** (`#0042`, het id
  van de speler) in de voet, zoals op een verzamelkaart.
- **Mijn kaarten** (`/players/{player}/kaarten`,
  `PlayerCardCollectionController`): de kaart van nu en de bewaarde
  seizoenskaarten (`player_card_seasons`) als verzameling, elk getekend met
  dezelfde component uit de cijfers en het level van toen. Geen mijlpalen en
  geen achterkant op een oude kaart: die horen bij nu.
- **Foto en rugnummer klappen in zodra ze er allebei staan**
  (`CardPersonalise.vue`): tot dan een opvallend blok met "1 van 2", daarna
  één regel met de foto, het nummer en "Aanpassen". Het rugnummer slaat
  zichzelf op (plus, min of typen); een opslaan-knop naast één getal is een
  stap die niemand verwacht.
- **Delen is één knop** (`CardShareActions.vue`) met eronder: delen via
  het deelmenu, naar Snapchat, opslaan als afbeelding, link kopiëren. De
  kind-link en de openbare deel-link staan in hetzelfde vak als regels met
  "Aan" of "Uit" die uitklappen, zodat de pagina niet drie keer delen zegt.
- **De deel-afbeelding krijgt een sticker** als er iets te vieren is:
  "NIEUW LEVEL" na een level-up, anders "+X GEGROEID" als de gemiddelde
  groei van het laatste rapport minstens drie punten is (`groeiSticker()`).

### Inschrijven en betalen: de instellingen per school

Scholen verschillen sterk in hoe ze inschrijven en innen, maar gebruiken
dezelfde bouwstenen. Daarom **één generiek model, per school instelbaar**:
`Support\Enrollment\EnrollmentSettings`. Standaarden in code, in
`schools.enrollment_settings` alleen de antwoorden van de school — dezelfde
afspraak als bij de rekenkern en de functies. Een nieuwe instelling krijgt zo
bij elke bestaande school vanzelf zijn standaard.

De eigenaar vult ze in via een **wizard van zes stappen** (Mijn bedrijf →
Inschrijven en betalen, `/instellingen/inschrijven`): aanbod en proefles,
kosten erbij (inschrijfgeld, kledingpakket), betalen (standaard betaalvorm,
goedkeuren, verlengen, opzegtermijn, storneringskosten), annuleren en ziekte,
kortingen, en het formulier (wachtlijst, aanmeldvelden, toestemmingen,
ontwikkelingslaag). Drie dingen die je niet moet omdraaien:

- **Eén formulier per onderwerp.** De eerste keer loop je de stappen achter
  elkaar door; daarna opent het overzicht elke stap los. Het is hetzelfde
  scherm, dus er is geen wizard én een instellingenpagina die uit elkaar lopen.
  Zolang de wizard niet is afgerond opent het menu-item stap één, niet een
  overzicht van standaarden die je nog nooit hebt gezien.
- **Elke stap slaat alleen zijn eigen velden op**, laag voor laag samengevoegd.
  Een school die alleen de proeflesprijs zet raakt haar andere antwoorden niet
  kwijt, en een stap kan nooit een andere instelling overschrijven.
- **Het is een enquête met uitleg, en meerdere antwoorden mogen.** Boven elke
  stap staat in gewone taal wat je hier beslist en wat het voor ouders
  betekent. Wat naast elkaar kan bestaan is een vinkjeslijst: aanbodvormen,
  instapmomenten (`enrollment.moments`: hele jaar, vóór een blok, losse
  training, kampen) en betaalvormen (`default_payment.types`). Alleen wat
  echt óf-óf is (goedkeuren, ziekte) is een keuzerondje, en dat zegt de
  vraag er dan bij. Een oude, enkelvoudige `default_payment.type` blijft
  gelden via `paymentTypes()` tot de school hem aanpast.
- **De stap Inschrijven laat zien wat een keuze doet, niet alleen wat hij
  heet.** Vier genummerde blokken, elk met een regel "Voor de ouder: …", en
  ernaast (op een telefoon: erboven) `onboarding/EnrollmentPreview.vue`: een
  nagebouwde inschrijfpagina met verzonnen namen en prijzen die meebeweegt
  met de vinkjes, plus een knop naar de echte pagina in een nieuw tabblad.
  Bovenaan tekent `onboarding/EnrollmentFlow.vue` de keten van klik tot kind
  in de groep — wie doet wat — en die verandert mee met goedkeuren en
  betaalvormen. Dezelfde keten staat bij Betalen en op de samenvatting.
- **De keuzes vullen nieuwe trainingen en nieuw aanbod voor.**
  `trainingDefaults()` zet los inschrijven, betaalwijze en goedkeuring in het
  trainingsformulier; de betaalvormen staan als extra betaalopties klaar bij
  nieuw aanbod. Per training of aanbod wijk je ervan af — de wizard is de
  standaard, niet de wet.
- **Na de laatste stap kom je op de samenvatting**, niet op het dashboard:
  `enrollment-settings/Index` vertelt in alinea's hoe het nu werkt ("Meldt een
  ouder zich aan, dan…"), met daaronder het telefoonnummer
  (`EnrollmentSettingsController::SUPPORT_PHONE`). Wie na negen vragen denkt
  "zo werk ik niet" moet niet gaan zoeken, maar bellen.
- **Wat een instelling elders al is, wordt geen tweede instelling.** De
  ontwikkelingslaag is `Feature::Ontwikkeling`; de wizard zet die functie en
  het menu, de routes en de taken lezen hem al. De aanbodsoorten die de school
  kiest bepalen wat er in het aanbodformulier staat (`ProductController`); een
  bestaand aanbod van een uitgezette soort blijft gewoon staan.

**Toestemmingen** (`consent_documents`) zijn een tabel en geen vinkje, omdat
een toestemming aan een **documentversie** hangt: een andere tekst is een
nieuwe versie, en wie de oude tekende heeft de nieuwe niet getekend. Vier
soorten (AVG, beeldrecht, gedragsregels, medisch) met een standaardtekst als
de school er nog geen heeft; AVG staat standaard op verplicht.

**Goedkeuren is standaard handmatig**; een school die het vertrouwt zet het op
automatisch, en dan bevestigt de betaling de inschrijving. **Proefles** is een
eigen aanbodsoort (`ProductType::Proefles`); de instelling zegt of hij
aanstaat en wat hij kost.

### Het datamodel van inschrijven en betalen

Zoveel mogelijk bestaande tabellen, uitgebreid. Geen tweede model ernaast.

| Ding | Tabel | Wat het is |
|---|---|---|
| Aanbod | `products` | Soort, doelgroep (`audience`: iedereen / keepers / veldspelers), leeftijd, capaciteit, data, aantal sessies, locatie, trainers, status |
| Betaalvorm | `payment_options` | Eenmalig, termijnen (n × bedrag) of abonnement (interval + bedrag). **Meerdere per aanbod**, precies één is de standaard |
| Ouder ↔ kind | `guardian_player` | Bestond al: één ouder met meerdere kinderen, een kind met twee ouders |
| Inschrijving | `enrollments` | Kind + aanbod + gekozen betaalvorm + order + ouderaccount; draagt de status |
| Order | `orders` + `order_lines` | De financiële kop per gezin; regels voor aanbod, inschrijfgeld, kledingpakket en korting |
| Betaling, abonnement | `payments`, `subscriptions` | Bestonden al; wijzen nu ook naar de order en de betaalvorm |
| Mandaat | `mandates` | Per **ouder** (die betaalt en tekent): alleen kenmerken van de provider. **Nooit een IBAN** |
| Korting | `discounts` | Gezin, vroegboek, volume, code; procent óf vast bedrag |
| Toestemming | `consents` | Wie, welk document, **welke versie**, wanneer |
| Wachtlijst | `waitlist_invitations` | Uitnodiging met token en tijdslimiet (onderdeel 6) |

Vier dingen die je niet moet omdraaien:

- **De standaard betaalvorm staat óók op het aanbod** (`billing_type`,
  `amount_cents`, `interval`), gezet door `Product::syncPaymentOptions()`.
  Alles wat er vóór de betaalvormen was (shop, inschrijfformulier,
  abonnementen, aankopen) leest die kolommen en blijft dus werken. Een
  abonnement als standaard maakt het aanbod "maandelijks", al het andere
  "eenmalig". In het formulier is het prijsblok de standaard en staan de
  extra's eronder.
- **Een aanbod heeft altijd minstens één betaalvorm.** De migratie gaf elk
  bestaand aanbod er een uit zijn betaalwijze; de factory doet hetzelfde.
- **Korting is een orderregel met een negatief bedrag.** Het totaal is de som
  van de regels (`Order::recalculate()`), en je ziet later nog waarom het
  lager was. Naam en bedrag op een regel zijn overgenomen op het moment van
  bestellen, net als bij een aankoop.
- **Een mandaat hoort bij de ouder, niet bij het kind.** Eerder stond het
  klantkenmerk op de speler; met orders die meerdere kinderen bundelen betaalt
  en tekent één ouder. `players.payment_customer_reference` blijft staan tot
  de incasso (onderdeel 7) op het mandaat is overgezet.

### De inschrijfflow en de statusmachines

**De openbare flow** (`/inschrijven/{slug}`, `PublicEnrollmentController` +
`enrollments/Public.vue`) loopt in stappen: aanbod → kind(eren) → jij
(account) → toestemmingen → betalen → overzicht → bevestigen. Zonder account
vooraf; het account ontstaat onderweg met het wachtwoord dat de ouder kiest.
Een ingelogde ouder krijgt naam, e-mail en kinderen voorgevuld en kan een
tweede kind erbij zetten. Meerdere kinderen gaan in één order.

- **Het formulier bevat geen regels.** Welke velden er staan, welke
  toestemmingen verplicht zijn, welke betaalvormen er zijn: alles komt van
  `Support\Enrollment\EnrollmentForm`, gevoed door de inschrijfinstellingen.
- **Het overzicht komt van de server** (`POST …/overzicht` →
  `OrderBuilder`), met dezelfde berekening als bij het indienen. Wat de ouder
  zag is wat er wordt vastgelegd. Kortingen zijn regels met een negatief
  bedrag; inschrijfgeld en kledingpakket zijn één keer per gezin; een
  abonnement betaal je niet vooraf (dat brengt zelf zijn termijnen voort).
- **Bij het indienen ontstaat alles** (`Actions\Enrollments\SubmitEnrollment`,
  één transactie): ouderaccount, speler, inschrijving, order met regels,
  toestemmingen met documentversie. In de groep komt het kind pas bij de
  bevestiging (`ConfirmEnrollment`). Vol aanbod → wachtlijst, zonder order.
- **Leeftijd, positie en "nog open" worden server-side gecontroleerd**, binnen
  de school. Een e-mailadres dat al een account heeft moet eerst inloggen:
  anders schrijft iemand een kind in op andermans naam.
- **Goedkeuren is een instelling.** Handmatig (standaard): de school keurt
  goed, dán gaat de order open en krijgt de ouder een betaalverzoek. Automatisch:
  de betaling bevestigt, of het is meteen rond als er niets te betalen valt.
- **`Actions\Payments\SettleOrder` is de enige plek** waar een betaalstand de
  order en de inschrijving raakt; de webhook (`SyncPayment`) en het handmatig
  markeren roepen hem allebei aan. De eerste betaalde termijn bevestigt; de
  order is pas betaald als alles binnen is. Een aankoop via een inschrijving
  krijgt geen eigen rekening (`SellProduct` met `withPayment: false`): die zit
  al op de order.
- **De proefles ontstaat uit de instellingen** (stap 1 van de wizard): staat
  hij aan, dan bestaat er een aanbod van het soort proefles met die prijs.

**Statusmachines** (`Support\Status\HasTransitions` op de enum,
`Models\Concerns\HasStatusMachine` op het model): `EnrollmentStatus`,
`PaymentStatus` en `SubscriptionStatus` kennen elk hun toegestane overgangen,
en `transitionTo()` weigert de rest. Geen losse vlaggetjes die elkaar kunnen
tegenspreken. Een status met de hand zetten omzeilt de machine; doe dat niet.

### Verlengen, opzeggen en annuleren

- **Een blok verlengt niet vanzelf.** `enrollments:lifecycle` (dagelijks)
  stuurt veertien dagen voor het einde een `VerlengUitnodiging` naar de ouder,
  één keer (`renewal_invited_at`), met een knop naar de inschrijfpagina.
  Staat `auto_renew_block` aan, dan gaat er geen uitnodiging: dan loopt een
  blok dat per maand betaald wordt gewoon door.
- **Dezelfde opdracht zet bevestigd op actief** zodra het aanbod begint, en
  op beëindigd zodra het voorbij is; en hij beëindigt abonnementen waarvan de
  opzegtermijn om is. Idempotent: elke stap kijkt naar de status van nu.
- **Opzeggen is niet stoppen** (`Actions\Subscriptions\PlanCancellation`): het
  abonnement gaat op "opzegging gepland" met een einddatum op de opzegtermijn
  uit de instellingen, en brengt tot dan gewoon rekeningen voort.
  `Subscription::active()` telt daarom alles wat nog factureert
  (`SubscriptionStatus::bills()`), niet alleen "actief".
- **Annuleren vóór de start volgt het restitutiebeleid**
  (`Support\Enrollment\RefundPolicy`, `Actions\Enrollments\CancelEnrollment`):
  kosteloos tot X dagen vooraf, daarna Y% ingehouden. Het bedrag komt op de
  inschrijving (`refund_cents`) en in de mail aan school én ouder; het
  terugbetalen zelf is een handeling van de school, de app boekt niets. Wat
  betaald was blijft betaald: dat is de historie. Ziekte of afwezigheid geeft
  standaard niets terug; de instelling zegt het anders en het formulier toont
  wat er geldt.
- **Wie mag wat**: annuleren en opzeggen kan de school, en de ouder van dít
  kind (`cancel` in `EnrollmentPolicy` en `SubscriptionPolicy`). Op het
  ouderscherm (`/billing`) staat per inschrijving wat annuleren nu oplevert,
  vóórdat je klikt.

### De wachtlijst

- **Vol aanbod blijft op de inschrijfpagina staan** (als de school de
  wachtlijst aan heeft) en een aanmelding wordt een wachtlijstplek: wél een
  speler en een ouderaccount, wél een deelname met status wachtlijst (zodat
  het aanbodbeheer hem ziet), **geen order en geen rekening**.
- **Uitnodigen** (`Actions\Enrollments\InviteFromWaitlist`, via de inbox of
  het deelnemersscherm) kan alleen als er plek is. Dan ontstaat de order, gaat
  hij open, en krijgt de ouder `PlekVrijgekomen` met een betaallink die
  verloopt op de tijdslimiet uit de instellingen (`capacity.invitation_days`,
  standaard drie dagen). Betaald op tijd → SettleOrder bevestigt en het kind
  komt in de groep.
- **Verlopen** doet `enrollments:lifecycle`: de plek vervalt (inschrijving op
  verlopen, rekening geannuleerd, deelname geannuleerd), de ouder hoort het
  (`UitnodigingVerlopen`), en **de volgende in de rij wordt automatisch
  uitgenodigd**. Dat is de enige plek waar de app zelf kiest wie er
  doorschuift; de eerste uitnodiging blijft een keuze van de school.
- Een wachtlijstplek zonder inschrijving (met de hand op de lijst gezet) gaat
  nog de oude weg (`PromoteParticipation`): meteen een plek en een rekening.

### Betalingen: wat er klaarstaat voor de aansluiting

Nog niet aangesloten (zonder `MOLLIE_KEY` hangt `NotConnectedGateway` eraan),
maar zo gebouwd dat het erin past:

- **Het mandaat hoort bij de ouder** (`Support\Payments\Mandates`,
  `mandates`-tabel): de eerste iDEAL-betaling op een order gaat met een
  klantkenmerk van de betaler (`sequenceType: first`), en de webhook geeft het
  mandaatkenmerk terug dat `SyncPayment` vastlegt. Alleen kenmerken, nooit
  een IBAN. Het oude klantkenmerk op de speler blijft werken voor abonnementen
  die er al liepen.
- **Geen incasso zonder vooraankondiging.** `payments:prenotify` stuurt
  `IncassoAankondiging` en zet `prenotified_at`; `payments:collect` schrijft
  pas af als dat minstens veertien dagen geleden is, en vraagt bij elke ronde
  opnieuw aan de provider of het mandaat nog geldt.
- **Na een mislukte, verlopen of gestorneerde betaling** stuurt
  `payments:remind` `BetalingMislukt` met een nieuwe betaallink, volgens het
  herhaalschema van de school (`dunning.days`, standaard 3, 7 en 14 dagen na
  de mislukking), en telt mee in `reminder_count`. Een abonnement volgt zijn
  betaling: mislukt → "betaling mislukt", betaald → weer actief.
- **Storneringskosten** (instelling) worden één keer per gestorneerde betaling
  als eigen rekening aangemaakt, met `parent_id` naar de oorspronkelijke.
- **Een ouder kan een incasso acht weken terugdraaien**
  (`Payment::isWithinChargebackWindow()`). Omzet uit incasso is in die periode
  dus nog niet zeker; dat is geen fout van de app maar van het betaalmiddel.

### Inschrijvingen

#### De openbare aanmeldpagina

`/inschrijven/{slug}` is de **aanmeldpagina** van een school: eerst het aanbod,
dan pas de gegevens. Andersom vraag je naam en geboortedatum van een kind
voordat iemand weet of er überhaupt iets bij zit.

- **Alleen aanbod waar je je op kunt aanmelden** staat erop: actief, status
  open, en niet vol. Iets tonen waar je niet op kunt is een dode klik.
- Per aanbod: omschrijving, data, locatie, leeftijd, prijs en hoeveel plekken
  er nog zijn.
- `?aanbod=12` opent meteen dat aanbod, zodat een school naast elk programma op
  haar eigen site een knop kan zetten. `/inschrijven` zonder slug werkt op het
  **subdomein** van de school.
- **Deze pagina mag in een iframe** (`SecurityHeaders` slaat `X-Frame-Options`
  over voor `enroll.*`): hij is bedoeld voor de eigen website van de school, en
  er staat niets achter een sessie.
- **Vol en leeftijd worden server-side gecontroleerd**, binnen `forSchool()`.
  Buiten die scope telt een query nul deelnemers, en dan lijkt een vol blok nog
  plek te hebben. Iemand met de pagina in een tabblad weet niet dat het blok
  inmiddels dicht zit.

Elke school heeft een **openbaar inschrijfformulier** op `/inschrijven/{slug}`.
Dat is, naast de gedeelde kaart, de enige route zonder inlog; de school komt
daar wél uit de URL, want er is geen ingelogde gebruiker. `HandleInertiaRequests`
zet de app-props voor `enroll.*` leeg, net als voor de kaart.

Een inschrijving is een **aparte tabel** (`enrollments`), geen speler. Pas als
de eigenaar goedkeurt (`Actions/Enrollments/ApproveEnrollment`) ontstaan in één
transactie de speler, het ouderaccount (of de koppeling aan een bestaand account
van dezelfde school) en de afspraak.

**Het soort product bepaalt de administratie**: een abonnement wordt een
`Subscription` met een eerste rekening, al het andere (kamp, rittenkaart, losse
training) een `Purchase` via `SellProduct`. Alles als abonnement wegschrijven
leverde een kamp met een maandfrequentie op.

#### Contant of online, en de betaallink

Op het formulier kies je **hoe je betaalt**: contant bij de school of online.
Automatische incasso staat er alleen bij als het gekozen product een abonnement
is — bij een kamp is "elke termijn afschrijven" een belofte over een termijn die
niet bestaat. **Wat niet kan, staat er niet**: zonder aangesloten provider
bestaat alleen contant, ook in de validatie.

**Betalen gebeurt pas ná goedkeuring.** Anders kan er geld binnenkomen van
iemand die de school afwijst, en terugbetalen zit niet in de app. Bij de
goedkeuring gaat `InschrijvingGoedgekeurd` naar de ouder: bij contant een zin
("dat reken je af bij de school"), bij online een knop.

Die knop is een **ondertekende, veertien dagen geldige link**
(`Support\Payments\PaymentLink` + `PublicCheckoutController`), buiten de inlog
om. Een net ingeschreven ouder heeft nog geen wachtwoord; hem eerst dat rondje
laten doen is precies waar iemand afhaakt. Wat dat veilig houdt: de handtekening
zit op het adres (sleutelen aan het id werkt niet), de pagina toont alleen
bedrag, omschrijving, voornaam en school, en de uitkomst komt net als anders van
de **webhook** — niet van de browser. `HandleInertiaRequests` zet de app-props
voor `public-pay.*` leeg, net als voor de kaart en het inschrijfformulier. Zo komt er nooit ongevraagd iemand in
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

**Excel is een net document, CSV is kaal.** `ExportWriter` zet in een xlsx
bovenaan de schoolnaam, de titel en de periode (samengevoegd over de
breedte), vette kolomkoppen op een lichte achtergrond, kolombreedtes naar
inhoud, datums als echte datums (`dd-mm-jjjj`), bedragen met euroteken en
twee decimalen, en een totaalregel waar dat zin heeft. Wat voor soort waarde
in een kolom staat zegt het overzicht zelf: `FormattedExport::types()` en
`totals()`, of per `Sheet` (`types`, `totals`). CSV blijft koppen en rijen:
dat is voor importeren, en een kopregel zit daar in de weg. De bestandsnaam
is `school-overzicht-datum`. Er is ook een `ReportsExport` (rapporten).

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

### Los inschrijven op een training, naast de groep

Een kind doet op **twee manieren** mee aan een training, en die bestaan
naast elkaar:

1. **Via de groep** — de school heeft het ingedeeld, of het heeft een
   abonnement (`products` van het soort *doorlopend*, dat sinds dit onderdeel
   een vast ritme en een groep heeft; zie hieronder). Wie in de groep zit is er
   gewoon, ook bij trainingen die later in die reeks worden ingepland.
2. **Los** — de ouder schrijft het kind in op precies die ene training
   (`training_enrollments`). De regels staan **op de training zelf**:
   `open_enrollment`, `age_categories` (leeg = iedereen), `audience`
   (positie), `capacity`, `price_cents`, `payment_methods` (online/contant) en
   `requires_approval`.

Wat je niet moet omdraaien:

- **De grens ligt op de server** (`Training::acceptsPlayer()`,
  `Actions\Trainings\EnrollInTraining`). Het scherm van de ouder grijst
  alleen uit wat toch niet kan, met de reden erbij. Leeftijd gaat op de
  categorie van de speler (KNVB-jaargang), positie op `ProductAudience`.
- **Vol wordt geteld, niet opgeslagen**: de groep plus de bevestigde losse
  aanmeldingen (`spotsTaken()`). Is het vol, dan wordt een aanmelding een
  **wachtlijstplek** zonder rekening. Meldt iemand zich af, dan krijgt de
  eerste op de wachtlijst bericht (`PlekVrijTraining`) en schrijft zélf
  alsnog in — geen automatische toekenning, dat gezin heeft misschien allang
  iets anders geregeld.
- **Goedkeuring vóór betaling.** Vraagt de training om goedkeuring, dan is
  een aanmelding eerst een *aanvraag*; de school keurt goed of wijst af (met
  bericht, `TrainingAanmeldingBeoordeeld`), en pas bij het ja ontstaat de
  rekening. Aanvragen staan in het aandacht-blok (`training_requests`).
- **De rekening is een gewone `Payment`** met `training_enrollment_id`, zodat
  hij meetelt in het financiële overzicht. **Contant bij de training is geen
  achterstand** (`Payment::isCashAtTraining()`): niet in het tabblad Te laat,
  geen herinnering, en de ouder ziet "contant te voldoen bij de training". De
  trainer vinkt bij de training af dat het binnen is; dan staat hij op betaald
  met methode contant.
- **Twee manieren van afmelden, bewust.** Wie in de groep zit meldt zich af
  voor die ene keer (`registration`) en blijft in de groep; wie los was
  ingeschreven wordt uitgeschreven en de plek gaat naar de wachtlijst.
- **`expectedPlayers()` telt de losse aanmeldingen mee**, dus afvinken,
  rapporten en de rapport-herinnering werken zonder wijziging. Op de
  aanwezigheidslijst staat er "los ingeschreven" bij, met de contantknop.
- **De ouder ziet drie stapels per kind** (`Support\Trainings\FamilyTrainings`,
  `FamilyTrainingList.vue`): Komend, Inschrijven, Geweest, met een wisselaar
  bij meer dan één kind. In de agenda staat een inschrijfbare training met
  een gestippelde rand en het label "inschrijven"; aantikken opent de
  inschrijving. Wie mag inschrijven is de ouder (`TrainingPolicy::enroll`);
  een kind met eigen inlog kijkt alleen.

#### Doorlopend aanbod als abonnement op een vast ritme

`ProductType::Doorlopend` heeft nu een rooster (`hasSchedule()`): dagen en
tijd staan in `products.schedule`, er hangt een groep aan, en
`ScheduleOffering` legt de trainingen **twaalf weken vooruit**;
`enrollments:lifecycle` legt er elke nacht een dag bij (`refresh()`). Wie het
abonnement afneemt (via de inschrijfpagina, `ConfirmEnrollment` →
`JoinOffering`) komt in de groep en staat daarmee op álle trainingen, ook de
later ingeplande. Een blok houdt zijn einddatum; alleen doorlopend rolt.

### Mijn trainingen en de rapport-herinnering

`/trainings/mijn` is het scherm dat een trainer op zijn telefoon openslaat: alleen
zijn eigen trainingen, per dag, eerstvolgende bovenaan. Het rooster van de school
blijft op `/trainings`.

**Een training zonder gekoppelde trainers telt als "van iedereen".** Koppelen
is informatief en veel scholen doen het niet; zou dit scherm strikt filteren,
dan is het bij die scholen altijd leeg. Dezelfde regel geldt voor de herinnering.

`Support\Trainings\ReportPrompts` is de rapport-herinnering: van **tien minuten
vóór** de eindtijd tot **vijf uur erna** staat er bovenaan het dashboard en bij
Mijn trainingen een blok met de spelers van die training en per speler of het
rapport gedaan is. Vier dingen die je niet moet omdraaien:

- **Het venster wordt server-side berekend** op `ends_at`. Een telefoon met een
  verkeerde klok zou het blok anders op het verkeerde moment tonen.
- **Het verdwijnt vanzelf** zodra alles is ingevuld of het venster voorbij is.
  Een herinnering die blijft staan nadat je hem hebt afgehandeld leer je negeren.
- **Het is een blok, geen pop-up.** Iets dat over je scherm springt terwijl je
  nog aan het afvinken bent klik je weg zonder te lezen.
- **"Gedaan" = een rapport met `reported_on` op de dag van de training.**
  Rapporten hangen bewust niet aan een training; een trainer schrijft over een
  speler, niet over een sessie. De datum is het enige eerlijke verband.

### Het trainer-account

Een trainer is **personeel, geen directie**. Hij komt met twee vragen — waar
moet ik zijn, en wie moet ik nog beoordelen — en zijn schermen beantwoorden
die. Een uitgeklede versie van het eigenaar-dashboard is geen trainerscherm:
dan staat er van alles waar hij niets mee kan.

- **Zijn dashboard bestaat uit twee eigen widgets**, `DashboardWidget::MyTrainings`
  en `MyPlayers`, gevoed door `Support\Dashboard\TrainerDashboard`. Wie alleen
  trainer is krijgt daarvoor een eigen standaardindeling
  (`WidgetRegistry::trainerLayout()`); de eigenaar houdt de schoolbrede en kan
  ze erbij zetten, want bij een kleine school geeft hij zelf training.
- **"Mijn spelers" leidt de groepen af uit de trainingen waar hij aan gekoppeld
  is.** Er is bewust geen koppeling trainer↔groep: een trainer valt in, ruilt
  en draait mee. Is hij nérgens gekoppeld, dan is de hele school van hem —
  dezelfde regel als `Training::scopeForTrainer`, want anders is het scherm bij
  elke school die niet koppelt leeg.
- **De lijst staat op wie het langst niets kreeg**, niet alfabetisch. Een
  alfabetische lijst is een telefoonboek; deze beantwoordt de vraag waarvoor je
  hem opent. Nooit beoordeeld telt als aandacht, en de grens is dezelfde dertig
  dagen als in het aandacht-blok.
- **Wat niet van hem is, zit server-side dicht.** Betalingen, abonnementen,
  aanbod, overzichten, huisstijl, verantwoording, de inschrijfwizard, spelers
  en groepen bewerken en het teamoverzicht van de beschikbaarheid geven alle
  een 403; de beheeromgeving een 404. Dat lag al vast in de policies — het menu
  is er de cosmetische kant van.

#### Een trainer ziet alleen zijn eigen werk

Dit is de tweede versie van deze regel, en hij draait de eerste om. Eerst zag
een trainer het hele ledenbestand ("alles zien, niets wijzigen"); de eigenaar
wilde dat een trainer als personeel alleen zijn eigen groepen en spelers ziet,
en dat is nu de regel. **Server-side**, niet alleen in het menu.

`Support\Trainers\TrainerScope` is de enige plek die zegt wat "zijn" is: de
groepen van de trainingen waar hij aan gekoppeld is, en de spelers daarin. Er is
bewust geen koppeling trainer↔groep — de planning weet al wie er stond, en een
invaller hoort er vanaf het moment van koppelen bij zonder tweede administratie.
`Player::visibleTo()` en `Group::visibleTo()` zetten die grens in elke lijst;
`PlayerPolicy::view/createReport` en `GroupPolicy::view` zetten hem op elke URL.

**Eén uitzondering, bewust:** is een trainer nérgens aan gekoppeld, dan is de
hele school van hem. Koppelen is bij veel scholen niet gebruikelijk, en een
trainer die na het inloggen nul spelers ziet denkt dat het product stuk is.
Zodra de school hem ergens aan koppelt, sluit de scope vanzelf.

Wat daarbij hoort:

- **Het rooster blijft schoolbreed zichtbaar** (kalender met de toggle Mijn /
  Alle): invallen en ruilen moet kunnen zien wat er staat. Afvinken en
  beoordelen kan hij alleen bij zijn eigen spelers.
- **Zijn menu is klein**: Trainingen, Spelers, Rapporten, Verjaardagen, Mijn
  account. Geen Klanten, Financiën, Mededelingen of Mijn bedrijf. "Spelers" is
  hetzelfde scherm als Klanten, begrensd en zonder ouders — die horen bij de
  klantrelatie en gaan ook niet onzichtbaar in de JSON mee.
- **Personeel, Locaties, Mijlpalen en het berichtenoverzicht zijn van de
  eigenaar.** Een afgelasting versturen mag de trainer nog (`create` op
  `Announcement`): dat komt van wie om zeven uur naar het veld kijkt.
- **Zijn dashboard**: de rapport-herinnering, drie eerstvolgende trainingen met
  afvinken en rapporten erbij, en de verjaardagen van zijn spelers (weg als er
  geen zijn). Geen kerncijfers — `DashboardWidget::ownerOnly()` dekt nu ook de
  schoolbrede cijfers. `/verjaardagen` is de "Bekijk meer" erachter, voor hem
  begrensd op zijn spelers.

### De snelle invulflow

`/trainings/{training}/rapporten` (`Reports\TrainingReportController` +
`reports/Quick.vue`) is het scherm dat een trainer na afloop op het veld
opendoet: alle spelers van die training achter elkaar. Het losse invulscherm
(`reports/Create.vue`) blijft voor "ik wil één speler beoordelen".

- **Opslaan gaat direct door naar de volgende speler.** Geen tussenscherm, geen
  bevestiging; wie op de kaartpagina uitkomt is zijn ritme kwijt.
- **De volgorde ligt vast** (de spelers van de training, alfabetisch), zodat
  "speler 3 van 8" ergens op slaat.
- **Overslaan en stoppen mogen allebei.** Elk rapport is bij het opslaan al
  binnen — er is geen concept dat je kunt kwijtraken — en wie je oversloeg komt
  aan het eind vanzelf weer langs. Het herinneringsblok telt hem intussen mee.
- **Het rapport krijgt de datum van de tráining, niet van vandaag.** Wie 's
  avonds laat afsluit hoort geen rapport van morgen te krijgen, en het is de
  datum waarop "gedaan" wordt geteld.
- **Aan het eind één samenvatting** (`reports/QuickSummary.vue`) met wie steeg,
  wie een level erbij kreeg en wie nog open staat. Per speler vieren zou acht
  keer hetzelfde blok opleveren; na de tweede kijkt niemand er meer naar.
- **De poort is `recordAttendance` op de training**: wie mag afvinken, mag ook
  beoordelen. Daardoor is het één flow voor trainer én eigenaar, en niet twee
  die uit elkaar lopen.

### Beschikbaarheid van trainers

`Support\Availability\TrainerAvailability` is de enige plek waar "kan die
trainer dan?" beantwoord wordt — voor zijn eigen scherm, het overzicht van de
eigenaar en de waarschuwing op het dashboard. Twee tabellen, want het zijn twee
dingen: `availability_rules` is het gewone ritme (zeven dagen × drie dagdelen,
een rij betekent *beschikbaar*), `availability_exceptions` is wat daarvan
afwijkt, met een begin- en einddatum.

- **Niets ingevuld is onbekend, niet onbeschikbaar** (`hasSet()` /
  `isAvailableAt()` geeft `null`). Zonder dat verschil kleurt bij elke school
  die dit nog niet gebruikt de hele planning rood, en dan kijkt niemand er meer
  naar.
- **Een uitzondering wint van het ritme, en de fijnste uitzondering wint.**
  "Die zaterdag alleen 's ochtends niet" gaat vóór "die hele week kan ik wel".
- **Het dagdeel gaat op de begintijd** van de training (`Daypart::forTime`):
  16:30–18:00 is een middagtraining, want dat is het moment waarop hij er moet
  zijn.
- **Bewust niet fijner dan dagdelen.** Beschikbaarheid in kwartieren vragen is
  een agenda bouwen die niemand invult.
- **Je beschikbaarheid is van jou.** De eigenaar ziet hem
  (`/personeel/beschikbaarheid`) maar vult hem voor niemand in; dat weigert
  `AvailabilityExceptionPolicy`.
- **De eigenaar wordt gewaarschuwd, niet geïnformeerd.** `conflicts()` levert
  twee losse signalen voor het aandacht-blok — een trainer die is ingepland op
  een moment dat hij niet kan, en een training waar helemaal geen trainer bij
  staat. Twee gesprekken, dus twee regels: bij het ene bel je iemand, bij het
  andere koppel je er een. Alleen veertien dagen vooruit, want een training van
  vorige week verplaats je niet meer. Een privétraining telt niet mee: die
  hoort bij één kind en heeft geen trainer in het rooster nodig.

### De rekenkern: rating, XP en level

`Support\Rating\RatingEngine` is de enige plek waar de drie getallen van de
spelerontwikkeling uit elkaar volgen. Ze betekenen verschillende dingen en dat
moet je niet door elkaar halen:

| Getal | Betekent | Komt uit | Kan dalen? |
|---|---|---|---|
| **Rating** | hoe goed, t.o.v. de leeftijdsgroep | de rapporten (`CalculatePlayerCard`) | ja |
| **XP** | inzet | aanwezig zijn, rapporten, groei | nooit |
| **Level** | brons · zilver · goud · elite | de XP | alleen bij een correctie |

- **De rating wordt nooit achteraf gecorrigeerd op leeftijd.** De trainer
  beoordeelt al relatief ("een goede 7 voor een O12"); het invulscherm zegt
  dat. Een kaart die afwijkt van wat hij opschreef vertrouwt niemand meer.
  Leeftijd is context op de kaart en in de uitleg, geen rekenfactor.
- **XP daalt nooit door prestaties.** Achteruitgang in een rapport kost niets;
  alleen een teruggedraaide aanwezigheid neemt zijn punten mee, want dat is een
  correctie van een fout. Elke XP is een regel in `xp_events` met een reden en
  een referentie — zo is een level altijd uitlegbaar, en levert twee keer
  dezelfde training afvinken geen dubbele punten op.
- **Groei-XP rekent met de gedempte kaartwaarde**, niet met het losse
  rapportcijfer. Zo levert een uitschieter geen berg XP op die de volgende
  week niet meer klopt.
- **Levels lopen puur op XP**, met per school een optionele minimale rating
  per level (standaard uit). Trouw komen brengt je naar goud, ook zonder
  talent — dat is het stimuleringsdeel. Haal je de minimale rating niet, dan
  zak je naar het level eronder, niet naar brons.

Alle getallen staan in `Support\Rating\RatingSettings`: standaarden in code,
in `schools.rating_settings` alleen afwijkingen — zoals bij de functies per
school. Standaard: 10 XP per aanwezigheid, 5 per rapport, 2 per punt groei
(plafond 30), levels op 0 / 150 / 400 / 900, drie rapporten als demping.

#### Leeftijdscategorie en seizoenskaart

`Support\Rating\AgeCategory` leidt de categorie af uit het **geboortejaar**
(KNVB-jaargangen, even banden O8 t/m O18+, peildatum 1 januari van het
seizoensjaar, seizoen vanaf augustus). Niet uit de groep: een speler kan in
twee groepen zitten. `players.age_category` is de laatst vastgestelde waarde,
opgeslagen om een overgang te kunnen zíen.

`players:categories` draait elke nacht. Gaat een speler een jaargang omhoog,
dan wordt zijn kaart eerst als **seizoenskaart** bewaard
(`player_card_seasons`: "Seizoen 2025/26 · O12") en wisselt daarna de
categorie. Geen rekenkundige truc bij de overgang: de demping over de laatste
rapporten vangt de hogere lat op, en de oude kaart blijft als herinnering staan
in plaats van te verdwijnen.

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
3. Overall = gemiddelde van de sub-scores.
4. Zonder rapporten: `null`, niet 0. Een lege kaart is geen slechte kaart.

**Precies bewaren, naar boven tonen.** `report_scores.score` heeft één decimaal
en `category_ratings` bewaart de doorgerekende waarde ongeafrond — daar hangt de
voortgangsberekening aan, en bij afronden per stap telt een halve punt groei als
een hele. Naar buiten wordt **altijd naar boven** afgerond: 74,5 wordt 75, nooit
74. Dat is een keuze in het voordeel van het kind.

Die afronding staat op **één plek**: `CalculatePlayerCard::afronden()`. Gebruik
die overal in plaats van `round()` of een cast — zodra er twee manieren van
afronden in de app zitten laat het ene scherm 74 zien waar het andere 75 zegt,
en dan gelooft niemand het meer.

De uitkomst wordt opgeslagen op `players` (`overall_rating`,
`category_ratings`, `rated_at`). Dat is een **momentopname**: de waarheid staat
in `reports`. Die kolommen staan niet in `$fillable` en worden alleen door
`CalculatePlayerCard::refresh()` gezet — nooit met de hand.

**Het rapport-invulscherm is de belangrijkste UX van de app.** Wat het snel
houdt, en dus niet mag sneuvelen:

- de cijfers van het vorige rapport staan **voorgevuld**; de trainer past alleen
  aan wat veranderd is;
- een cijfer is **een schuif van 1 tot 10 in stappen van een tiende**, met de
  waarde groot ernaast en een plus- en minknop voor het laatste tiende. Een
  trainer denkt in "een zeven, maar wel een goeie"; met hele cijfers moest hij
  kiezen tussen 7 en 8 en verdween precies het verschil dat hij zag;
- **de cijfertoetsen 1-9 en 0 blijven werken** en vullen de actieve rij met een
  heel cijfer. Op een laptop is tikken sneller dan slepen, en dat rapport moet
  in dertig seconden klaar;
- de schuif is 44px hoog met een grijper van 32px en `touch-action: none`.
  Zonder dat laatste scrollt de pagina mee zodra je verticaal afwijkt, en dan
  springt het cijfer terug;
- opslaan zit in een vaste balk onderaan, binnen duimbereik;
- de toelichting is optioneel en breekt het ritme niet.

### Na het opslaan: wat er veranderde

`Support\Reports\ReportOutcome` legt vast wát een rapport aan de kaart deed;
`components/ReportCelebration.vue` toont het. Dit is het moment waarop een
trainer ziet dat zijn werk aankwam, en dus geen bijzaak.

- **Twee losse stappen, geen toestand**: `snapshot()` vóór het opslaan,
  `changes()` erna. De opslag-actie hoeft er niets van te weten.
- **Dezelfde cijfers als de kaart.** De deltas komen uit `breakdown()` en dus
  uit `CalculatePlayerCard::afronden()`. Apart rekenen zou "+3" opleveren waar
  de kaart "+2" toont, en dan gelooft niemand het meer.
- **Alleen wat veranderde**, hooguit drie categorieën, grootste eerst. Een
  categorie die er nog niet was telt niet als groei: van niets naar 70 is geen
  sprong van zeventig punten.
- **Bij een level-up blijft het oude frame 900 ms staan**, waarna de kaart met
  een flits omslaat (`displayLevel` en `flash` op `PlayerCardVisual`). Zo zie je
  de upgrade gebeuren in plaats van dat hij er al was toen de pagina laadde.
  Alleen omhoog wordt gevierd; zakken gebeurt hooguit door een correctie.
- **Het is een balk onderaan, geen dekkend scherm, en hij sluit niet vanzelf.**
  Een teller die de knop "Volgende speler" weghaalt terwijl je nog leest is
  erger dan één tik extra.
- **"Volgende speler"** is de eerste actieve speler zonder rapport vandaag,
  alfabetisch, waar deze gebruiker een rapport voor mag maken. Zo loopt een
  trainer na de training zijn groep af zonder terug naar de lijst.

De wijziging reist als **flash** (`reportResult`) mee naar de kaartpagina. Na
een verversing is hij weg: hij hoort bij die ene opslag, niet bij de pagina.

### Ontwikkelingsdoelen (Fase 7)

- `goals`: één actief doel per speler per categorie; `start_rating` = kaartcijfer op het moment van stellen, `target_rating` = rapportcijfer × 10. Een nieuw doel in dezelfde categorie annuleert het oude.
- **Het streefcijfer heeft een decimaal**, zoals een rapportcijfer: "6,7" is een ander doel dan "7". Dat is op de kaart precies 67, dus de kolom blijft een heel getal; `Goal::ratingFromGrade()` en `gradeFromRating()` zijn de enige twee plekken die omrekenen, en overal op het scherm staat het cijfer met komma. `GoalProgress` geeft naast `on_track` ook `track`: behaald · op koers · net niet (tot vijftien punten onder waar je hoort te zijn) · achter.
- **Een eigen doel mag een richtpunt dragen** (optioneel streefcijfer), maar gemeten wordt het niet: geen balk, geen "op koers", afvinken doe je zelf.
- **Een eigen doel** (`Goal::CUSTOM`, categorie `overig` + `custom_label`) gaat over iets wat niet in de zes categorieën past: "uitverdedigen met links". Er is geen cijfer om aan af te meten, dus `target_rating` is leeg, er is geen "op koers" en er staat geen balk — een percentage verzinnen bij iets wat je niet meet is erger dan het weglaten. De trainer vinkt zo'n doel zelf af (`POST /goals/{goal}/behaald`); verlopen gaat wél vanzelf, want een einddatum betekent overal hetzelfde. Meerdere eigen doelen naast elkaar mogen: het zijn verschillende dingen, geen twee metingen van dezelfde categorie.
- **Vieren staat op één plek** (`Actions\Goals\AchieveGoal`), of het doel nu vanzelf gehaald wordt of met de hand wordt afgevinkt. Voor een kind is er geen verschil tussen die twee, dus het bericht aan de ouders hoort ook hetzelfde te zijn.
- `goals.category` is bewust **geen enum-cast**: `overig` hoort niet in `ReportCategory`, want die enum bepaalt waarop een speler beoordeeld wordt. `Goal::label()` en `Goal::describe()` maken er leesbare tekst van.
- "Op koers" = afgelegde weg (start → nu → streef) ≥ verstreken tijd (start → vandaag → einddatum). Zie `Support\Goals\GoalProgress`. Geen extra begrippen.
- Beoordelen gebeurt alleen in `Actions\Goals\EvaluateGoals`, aangeroepen vanuit `StoreReport` na de kaartberekening: gehaald → `DoelBehaald` naar ouders + speler, badge `doel_gehaald`, mijlpaal in de tijdlijn; einddatum voorbij → `missed`.
- Trainer/eigenaar stelt en stopt (`GoalPolicy`), ouder/speler ziet alleen. Het rapportscherm toont per categorie een chip "doel 80", niets meer.

### Profielfoto's

`Support\Media\ProfilePhoto` is de enige plek waar een foto binnenkomt, voor
spelers en accounts allebei. Drie dingen die die klasse doet en die je niet
moet weglaten:

- **Vierkant maken en verkleinen naar 512 pixels.** De foto komt op een rond
  medaillon en op de spelerskaart; een liggende foto die met CSS wordt
  bijgesneden ziet er op elke plek net anders uit. En een telefoonfoto van vier
  megabyte in een medaillon van veertig pixels is verspilling van de bundel van
  een ouder die langs het veld staat.
- **Het oude bestand opruimen.** Anders blijft elke poging staan.
- **Een willekeurige bestandsnaam van veertig tekens**, niet het id. De foto van
  een kind hoort niet te raden te zijn aan de hand van een nummer in een URL —
  hij staat namelijk ook op een gedeelde kaart.

`photo_path` staat bij `Player` en `User` **niet in `$fillable`**: hij gaat
alleen via die klasse. Wie wat mag staat in de policies: `updatePhoto` op de
speler (de eigenaar, de ouders van dít kind, en het kind zelf met een eigen
inlog — het is hun kaart; de trainer niet) en `update` op de gebruiker (jezelf,
of de eigenaar binnen zijn eigen school). **Geen svg**: dat is uitvoerbare
opmaak op een deelbare pagina.

**De foto komt vroeg, maar wordt niet afgedwongen.** Na het activeren van een
ouder- of speleraccount komt eerst `/welkom/foto` (`PhotoPromptController`):
de kinderen zonder foto, met per kind de kiezer, en "Later doen". Een
verplichte foto is waar iemand afhaakt. Wel blijft de herinnering staan tot
de foto er is: "+ Foto toevoegen" op het kaartje van het kind op het
gezinsdashboard, de knop op de kaart zelf (spelerdashboard én kaartpagina),
en een groen omrand fotovak onder de kaart (`#foto`). Kiezen gaat via
`PhotoUpload`: op een telefoon een cameraknop (`capture="user"`) én de
galerij, en daarna `PhotoCrop` — schuiven en zoomen in een vierkant vak,
uitvoer 512×512, zodat het gezicht op de kaart staat en niet het midden van
een liggende telefoonfoto.

De gedeelde kaart toont de foto wél en de achternaam niet. Dat is een bewuste
afweging: een foto zonder naam of school laat een vreemde niets doen, en zonder
foto is de kaart voor een kind de helft minder waard.

### Inzageverzoek (was: Privacy en bewaartermijn, fase 8)

Het aparte Privacy-scherm is **weggehaald**. Het liet een school een
bewaartermijn vastleggen en signaleerde welke oud-leden die termijn voorbij
waren. In de praktijk was dat een scherm waar niemand kwam, en een instelling
die nergens toe leidde wekt de indruk dat er iets mee gebeurt. Bouw het niet
terug zonder dat een school er zelf om vraagt.

Wat er wél staat, en waarom:

- **Inzageverzoek per speler.** `Players\PlayerDataController` +
  `Support\Exports\PlayerDataExport` zetten alles van één speler in één werkmap:
  profiel, ouders, rapporten, cijfers, aanwezigheid, doelen en betalingen. De
  knop staat op de pagina van die speler, want daar stelt een ouder de vraag.
  Deze export staat bewust **niet** in `ExportRegistry` — dat register is voor
  schoolbrede overzichten die je uit een lijst kiest.
- **Verwijderen** loopt via de gewone weg onderaan de spelerspagina, met
  `PlayerPolicy`. Er is geen tweede knop die hetzelfde doet.
- **`players.deactivated_at` blijft**, gezet in een `saving`-hook op het model —
  niet in een controller, want een speler gaat op meer dan één plek op
  niet-actief en de datum mag niet van de plek afhangen. Staat niet in
  `$fillable`. Dat is een feit over de speler, geen instelling.
- Alles hier is van de **eigenaar**. Een trainer beslist niet welke gegevens van
  andermans kind het gebouw uit gaan.

### Het aanbod (was: Producten)

`products` heet in de app **Aanbod** (`/aanbod`): dat is wat een voetbalschool
verkoopt. Eén lijst, geen twee — een aanbod is tegelijk het ding met een prijs
en het ding met data, plekken en trainers.

`App\Enums\ProductType`: doorlopend, blok, kamp, losse training, privétraining,
small group, rittenkaart, overig. Vier afspraken:

- **Betalen is een eigenschap, geen soort** (`App\Enums\BillingType`: eenmalig /
  maandelijks). Een blok van zes weken kan €120 ineens zijn of €30 per maand, en
  dat is dezelfde training. De administratie volgt de **betaalwijze**:
  maandelijks wordt een `Subscription`, eenmalig een `Purchase`. Het oude type
  `abonnement` beschreef juist die betaalwijze en heet daarom nu `doorlopend`.
- **Een blok, kamp of small group krijgt een gewone groep** (`groups.product_id`)
  met gewone trainingen (`Actions\Offerings\ScheduleOffering`). Daar zit de
  knoop met de rest van de app: aanwezigheid, rapporten, agenda en Mijn
  trainingen werken zonder één regel wijziging. Opnieuw roosteren raakt alleen
  wat nog moet komen — wat geweest is draagt aanwezigheid.
- **"Vol" wordt niet opgeslagen** maar geteld: capaciteit min bevestigde
  deelnemers (`Product::isFull()`). Een opgeslagen "vol" blijft staan zodra
  iemand afzegt, en dan weigert een school een plek die er wel is. `status` kent
  daarom alleen concept / open / gesloten.
- **Meedoen is meer dan betalen.** `participations` draagt de status
  (ingeschreven / wachtlijst / geannuleerd) en de rekening die eraan hangt;
  `Actions\Offerings\JoinOffering` zet de speler er in én in de groep. Alleen
  wie echt meedoet komt in de groep: iemand op de wachtlijst hoort niet op de
  aanwezigheidslijst van de eerstvolgende training.

#### Locaties

`locations` is een echt ding geworden; het was een los tekstveld bij elke
training, elk aanbod en elk moment. Dat betekende elke keer opnieuw intikken, en
drie schrijfwijzen van hetzelfde sportpark. Beheren doet de eigenaar op
`/locaties` (Mijn bedrijf); een trainer kiest er een bij het inplannen.

- **De naam blijft naast de verwijzing staan.** `location_id` zegt welke locatie
  het is, de kolom `location` houdt de naam vast zoals die op dat moment was —
  dezelfde regel als bij een aankoop, die naam en bedrag overneemt. Een locatie
  hernoemen mag de agenda van vorig seizoen niet herschrijven.
- **De relatie heet `venue()`, niet `location()`.** Anders levert
  `$training->location` de ene keer een string op en de andere keer een model,
  afhankelijk van wat er toevallig geladen is.
- **Verwijderen bestaat niet**, op niet-actief zetten wel. Wat er in de agenda
  van vorig seizoen staat hoort te blijven kloppen.
- De migratie heeft bestaande teksten omgezet naar locaties, per school en per
  unieke naam. Anders begint elke school met een lege lijst terwijl haar
  trainingen wél een adres hadden.

#### Betalen per soort aanbod

- **Eenmalig of per maand staat los van het soort** (`billing_type`), en de
  administratie volgt de betaalwijze: maandelijks wordt een `Subscription` met
  termijnen, eenmalig een `Purchase` met één rekening. Een blok dat per maand
  betaald wordt is dus een abonnement met een einddatum.
- **Bij het inschrijven staat er wat je betaalt en wanneer**: bedrag, frequentie
  en één zin die zegt wat er straks gebeurt ("je rekent af bij de school",
  "je krijgt een betaallink na goedkeuring", "je betaalt pas als er plek is").
  Een bedrag zonder "wanneer" laat een ouder gokken of er vanavond iets van zijn
  rekening gaat.
- **Het deelnemersscherm zegt wie er nog moet betalen** (betaald / openstaand /
  te laat, met bedrag) en "X van Y betaald". Dat is de vraag die een school
  stelt op de dag dat het kamp begint, en dan wil je niet eerst in het
  betalingenscherm gaan zoeken.
- **`/payments` filtert op aanbod.** Een rekening hangt aan een aankoop of aan
  een abonnement; allebei wijzen ze naar het aanbod.
- **Te laat is: de vervaldag is voorbij**, niet "de vervaldag is aangebroken".
  `Payment::isOverdue()` gebruikte `isPast()` op een datumkolom (middernacht) en
  zei daardoor op de dag zelf al "te laat", terwijl het tabblad Te laat die dag
  niet meetelde. Twee waarheden over hetzelfde woord.

#### Vol, en de wachtlijst

- **Vol aanbod blijft op de aanmeldpagina staan**, met een wachtlijst. "Kom over
  drie maanden nog eens kijken" is hoe je een gezin kwijtraakt. Gesloten en
  onzichtbaar aanbod verdwijnt wel: daar valt niets te wachten.
- **De server bepaalt of het vol is**, niet het formulier: iemand met de pagina
  in een tabblad weet niet dat de laatste plek net weg is. `enrollments.waitlist`
  legt vast dat het bij het insturen vol zat.
- **Op de wachtlijst staat niets open.** Goedkeuren maakt wel de speler en het
  ouderaccount aan — anders kan de school niemand bereiken — maar geen abonnement,
  geen aankoop, geen rekening. Dat ontstaat pas bij het doorschuiven
  (`Actions\Offerings\PromoteParticipation`). Betalen voor een plek die er niet
  is, is het soort fout waar een school een half jaar over hoort.
- **De school kiest wie er doorschuift.** De lijst staat op volgorde van
  aanmelden, maar automatisch de bovenste pakken gaat voorbij aan wat een school
  weet: dat er al gebeld is, dat een gezin het ergens anders heeft geregeld.
- **Doorschuiven kan niet als het vol is.** Anders staat er een kind op het veld
  waar geen plek voor is. Iemand van de lijst halen maakt de plek vrij en haalt
  hem ook uit de groep, zodat hij niet op de aanwezigheidslijst blijft staan.

#### Privétraining: momenten in plaats van inschrijven

Een blok schrijf je je op in; een privétraining **boek je**. De school zet in
`slots` neer wanneer welke trainer kan (`/aanbod/{id}/momenten`, per stuk of een
reeks weken), en een ouder kiest daaruit in de shop.

- **Eén moment, één boeking.** `player_id` gevuld is bezet; een inschrijving die
  nog op goedkeuring wacht houdt een moment óók vast (`enrollment_id`), anders
  boekt de volgende ouder hetzelfde uur terwijl de eerste nog wacht. Het boeken
  zelf gebeurt in een transactie met `lockForUpdate`: twee ouders die tegelijk
  klikken mogen niet allebei dat uur krijgen.
- **Bij het boeken ontstaat een echte training** (`trainings.slot_id`). Daardoor
  staat dat uur in de agenda en bij Mijn trainingen, en werken aanwezigheid en
  rapporten precies als bij elke andere training.
- **Daarvoor mag een training zonder groep**: een privétraining is één kind, geen
  groep. `Training::expectedPlayers()` valt dan terug op de speler van het
  moment, en `Training::label()` geeft de naam van het aanbod. `VisibleTrainings`
  en `TrainingPolicy` kijken daarnaast naar het moment, anders ziet een ouder
  zijn eigen afspraak niet.
- **Een boeking terugdraaien laat de rekening staan.** Wat er is afgesproken
  hoort in de historie; of er iets terugbetaald wordt is een gesprek tussen
  school en ouder, geen automatische boeking.

**Een maandbedrag bij een blok stopt standaard op de einddatum**
(`products.stops_at_end`). Een blok van zes weken dat na afloop blijft
doorschrijven is precies waar een ouder boos over wordt; een school die
doorlopende training verkoopt zet het uit.

### Producten (Fase 16)

`plans` heette naar de tijd dat een school alleen abonnementen verkocht. Het is
nu **`products`**, met `App\Enums\ProductType`: abonnement, rittenkaart, losse
training, kamp, overig. Eén prijslijst, want twee lijsten naast elkaar betekent
dat je bij elke vraag moet nadenken waar iets ook alweer staat.

- **Het type bepaalt het gedrag, niet alleen de naam.** Een abonnement heeft een
  frequentie, een rittenkaart heeft beurten. Wat niet bij het soort hoort wordt
  bij het opslaan **leeggemaakt** — een kamp met een maandfrequentie is een veld
  dat later niemand meer snapt.
- **`vat_rate` staat per product.** Sportlessen vallen in Nederland vaak onder
  het lage tarief en soms onder een vrijstelling; dat verschilt per school. De
  ingevulde prijs is wat de ouder betaalt, dus inclusief btw;
  `Product::amountExclVatCents()` rekent terug.
- **Nul euro mag.** Een proefles is gratis, en levert dan ook geen rekening op.

#### Aankopen versus abonnementen

Een **abonnement** loopt door en brengt telkens een nieuwe rekening voort; dat
blijft `Subscription`, met termijnen en incasso. Al het andere is een
**`Purchase`**: één keer afnemen, één rekening. `PurchaseController` weigert
daarom een abonnement — die twee door elkaar halen levert dubbele rekeningen op.

Naam en bedrag worden **overgenomen** uit het product, niet opgezocht. Een
prijsverhoging of een hernoeming raakt een gedane afspraak dus niet, precies
zoals bij abonnementen. `product_id` is nullOnDelete: die verwijzing is voor het
overzicht, niet voor de waarheid.

Intrekken zet de aankoop op `cancelled` en verwijdert niets. Wat er is afgenomen
hoort in de historie te blijven, en de rekening die eraan hangt ook.

#### Beurten van een rittenkaart

Een kaart zonder afschrijven is een prijslijst. `Actions\Products\ConsumeCredit`
haalt er een beurt af op het moment dat de trainer iemand **aanwezig** meldt —
niet bij het aanmelden. Wie zich aanmeldt en niet komt heeft niets afgenomen, en
dat verschil is precies waarom `registration` en `status` twee losse velden zijn.

Drie eigenschappen die je niet moet weghalen:

1. **De oudste bruikbare kaart gaat eerst.** Die verloopt als eerste; andersom
   raakt een ouder beurten kwijt die hij had kunnen gebruiken.
2. **Het is omkeerbaar.** `attendances.purchase_id` legt vast van welke kaart de
   beurt kwam, dus een trainer die zich vergist krijgt hem terug op dezelfde
   kaart — ook als die inmiddels verlopen is.
3. **Zonder kaart gebeurt er niets.** Aanwezigheid vastleggen mag nooit
   stuklopen op de administratie.

### Het financiële overzicht

`/payments` heeft vijf tabbladen die elk één vraag beantwoorden: wat kwam er
binnen, wat staat er open, wat is te laat, wat komt eraan, en alles. De filters
staan op één plek (`Support\Payments\PaymentQuery`), want de lijst en het
totaal onder de kop moeten dezelfde rijen tellen. Stonden ze twee keer, dan
wijzen ze vroeg of laat naar iets anders — en een boekhouder die een verschil
van drie euro vindt belt niet over drie euro maar over de vraag of hij het
systeem kan vertrouwen.

Twee dingen die je niet moet omdraaien:

- **De periode filtert op een andere kolom per tabblad.** "Ontvangen in maart"
  gaat over `paid_at`, "openstaand in maart" over `due_on`. Dat is geen detail:
  een rekening van februari die in maart betaald wordt hoort bij de omzet van
  maart.
- **Btw wordt per regel teruggerekend, niet over het totaal.** Negen procent
  over een rittenkaart en eenentwintig over een shirt bij elkaar optellen en er
  één percentage vanaf halen geeft een getal dat nergens op slaat.

**Een rekening bewaart zijn eigen btw-tarief**, net zoals hij zijn eigen bedrag
bewaart: product → abonnement of aankoop → betaling, en elke stap neemt over.
Een omzetoverzicht van vorig jaar mag niet veranderen doordat iemand vandaag het
tarief van een product aanpast.

### De verjaardagsmail

`players:birthday` draait elke ochtend om 08:00. Vier eigenschappen:

- **De school zet hem zelf aan** (`schools.birthday_greeting`, standaard uit).
  Het is een berichtje met haar naam eronder.
- **Idempotent via `players.greeted_on`.** Twee keer draaien feliciteert niet
  twee keer — precies het soort fout dat je klanten opmerken.
- **Op dag en maand**, en alleen bij actieve spelers.
- **Naar de speler zelf als die een eigen inlog heeft, anders naar de ouders.**
  Allebei zou betekenen dat een kind van acht en zijn moeder allebei
  "gefeliciteerd, jij bent jarig" krijgen.

Een lege eigen zin valt terug op de standaardtekst; een lege felicitatie is
erger dan geen.

### Zoek je school

`/scholen/zoeken` (`Auth\FindSchoolController`) is de wegwijzer achter "Nog
geen account? Zoek je school" op de inlogpagina: typ de naam, kies de school,
en je staat op haar openbare inschrijfpagina. Bewust karig — alleen actieve
scholen, alleen naam en logo, hooguit tien, pas vanaf twee tekens en met een
throttle — want dit is geen lijst van al onze klanten maar een wegwijzer voor
wie de naam al kent.

### Demo-betalingen

`Support\Payments\DemoGateway` is een betaalprovider die doet alsof, voor
demo's: de hele flow (rekening → betaalkeuze → een iDEAL-achtig scherm met
bankkeuze → bevestiging → status betaald, ook het mandaat bij een abonnement
en de incasso erna) zonder dat er een cent beweegt. Vier regels:

- **Alleen aan met `PAYMENTS_DEMO=true`, en nooit naast een Mollie-sleutel**
  (`AppServiceProvider`). Op productie is het een blokkerend punt in
  `playerpath:check`.
- **De uitkomst gaat door dezelfde deur als een echte webhook**:
  `DemoCheckoutController::complete` roept `SyncPayment` aan. Order,
  inschrijving, mandaat, abonnement en mails werken dus zonder er iets van
  te weten, en er is geen tweede pad dat een status zet.
- **Het scherm zegt overal dat het nep is** (`billing/DemoPay.vue`), en elk
  betaalscherm in de app draagt de gedeelde prop `paymentsDemo`
  (`GatewayNotice` toont dan een demo-melding, de knoppen zeggen "(demo)").
- **De link naar het demoscherm is ondertekend** en komt alleen van de
  gateway; zonder demo geeft het adres een 404.

### Betalingen met Mollie (Fase 9)

- De app praat alleen met `Support\Payments\PaymentGateway`. Zonder `MOLLIE_KEY`
  in `.env` hangt daar `NotConnectedGateway` aan en start er nergens een
  betaling; een sleutel toevoegen zet de hele keten aan zonder schermwijziging.
- **Geld gaat via centen naar `"12.50"`**, met `intdiv` en het restant, nooit
  via een float. Zie `MollieGateway::toAmount()`.
- **De status komt uit de bedragen, niet uit `_links`.** Elke stornering telt
  meteen; een terugbetaling alleen als hij volledig is, anders zou een tientje
  korting de hele omzet laten verdampen.
- **De webhook gelooft niets.** Mollie stuurt alleen een id; wij halen de stand
  zelf op. Altijd 200 terug, ook bij een onbekend id: een foutcode laat Mollie
  eindeloos herhalen en verraadt of een id bestaat. Zoeken gebeurt met
  `withoutSchoolScope()`, want er is geen ingelogde gebruiker; daarna wordt de
  school expliciet gezet met `forSchool()`.
- **`Actions\Payments\SyncPayment` is de enige plek die een betaalstand zet**,
  en is idempotent: Mollie herhaalt webhooks, en twee bevestigingsmails voor
  één betaling is fout.
- **`payments:generate` maakt de rekeningen**, dagelijks en idempotent op
  `period_start`. Een termijn loopt vanaf de startdag van het abonnement, niet
  per kalendermaand: wie op de 20e begint betaalt telkens op de 20e.
- **Termijnen** (`subscriptions.installments`) splitsen het periodebedrag via
  `Support\Money\SplitAmount`; de restcenten gaan naar de eerste termijnen,
  zodat de som exact klopt.
- `payments:remind` is ook zonder provider nuttig: drie dagen respijt, hooguit
  eens per twee weken per betaling, alleen naar de ouders van dat kind.
- **Doorlopende incasso**: de eerste betaling die de ouder zelf doet legt het
  mandaat vast (`sequenceType: first` met een klantkenmerk op de speler, niet
  op het ouderaccount — een gezin kan per kind een andere rekening hebben).
  Daarna schrijft `payments:collect` af. Die ronde vraagt **elke keer opnieuw**
  of het mandaat nog geldig is in plaats van dat bij onszelf te onthouden: een
  bank of ouder kan het intrekken, en afschrijven zonder mandaat levert een
  stornering plus een boze ouder op. Een fout bij één gezin stopt de ronde niet.

### Communicatie (Fase 10)

- `announcements`: `group_id` leeg = hele school. `recipients_count` wordt bij
  het versturen vastgelegd en **nooit herberekend**: wie er toen in de groep zat
  is de waarheid, en een speler die vandaag vertrekt heeft het bericht wél gehad.
- Ontvangers via `Support\Communication\AnnouncementAudience`: ouders **en**
  spelers met een eigen inlog, alleen van actieve spelers, en nooit dubbel.
- **Versturen gebeurt na de transactie.** Een mislukte opslag mag nooit alsnog
  honderd mails opleveren; die krijg je niet terug.
- **Een training afzeggen verwijdert hem niet.** Hij blijft als afgezegd in het
  rooster staan — "er stond een training die niet doorging" is iets anders dan
  "er stond niets" — en de reden is verplicht, want die komt letterlijk in het
  bericht. Terugzetten stuurt bewust géén bericht: dat wil de trainer zelf
  formuleren.
- **Trainers mogen ook mededelingen sturen**, niet alleen de eigenaar. Een
  afgelasting komt van wie om zeven uur naar het veld kijkt.
- **Verstuurde berichten staan ingeklapt**: titel, datum en ontvangergroep;
  de tekst klapt per bericht uit. Twintig berichten voluit is een lap tekst
  waar je doorheen moet scrollen om er één te vinden.
- **Meldingsvoorkeuren gelden alleen voor mail** (`users.notification_preferences`,
  zie `User::wantsEmail()`). In-app meldingen zijn niet uit te zetten: anders
  mist iemand een afgelasting en heeft de school geen enkele manier meer om hem
  te bereiken. Een lege voorkeur betekent "alles aan", zodat een nieuwe soort
  bestaande gebruikers niet stilzwijgend afmeldt.

### White-label (Fase 11)

- Een school kiest **een logo en één merkkleur**, meer niet. Statuskleuren,
  grafiektinten en de spelerskaart blijven van PlayerPath: "waarschuwing" hoort
  overal hetzelfde te betekenen, en het gecontroleerde contrast van de grafieken
  mag een school niet per ongeluk slopen.
- De kleur komt als `<style>` in de `<head>` (zie `ShareBranding` +
  `app.blade.php`), **niet** via JavaScript. Anders ziet elke bezoeker eerst een
  flits PlayerPath-groen.
- `Support\Branding\BrandColor` bepaalt de tekstkleur op die merkkleur zelf,
  door te meten welke van wit/bijna-zwart het meeste contrast geeft — niet door
  wit te forceren. Haalt geen van beide 4,5:1 (dat gebeurt bij felle
  middentinten), dan schuift alleen de **helderheid** op tot het wel kan, met
  behoud van tint. De kleur weigeren zou betekenen dat een school haar merk niet
  mag gebruiken; de eis verlagen dat ze haar eigen knoppen niet meer leest.
- **Het subdomein bepaalt alleen hoe het eruitziet.** `Branding::fromHost()`
  levert de school voor logo, naam en kleur; welke data je ziet blijft
  `SetCurrentSchool` op basis van het account. Kom je als eigenaar van school A
  binnen op het adres van school B, dan zie je jouw eigen merk en jouw eigen
  data. Zonder `APP_DOMAIN` wordt er geen subdomein afgeleid.
- **De gedeelde spelerskaart krijgt geen huisstijl.** Een logo zou net zo goed
  verraden bij welke school het kind zit als de naam.
- E-mails gaan uit naam van de school (`Notifications\Concerns\SendsFromSchool`).
  Alleen de afzendernaam; het adres blijft van het platform, want een eigen
  afzenderadres vraagt SPF- en DKIM-records bij de school zelf. Zie hieronder.

### De e-mails

Mail is het enige deel van het product dat je niet in de app kunt controleren:
je ziet hem pas als hij verstuurd is, en dan is het te laat. Daarom staat alles
wat een mail eruit laat zien op **één plek**, en is er een manier om ze
allemaal tegelijk te bekijken.

#### De schil

De gedeelde schil staat in `resources/views/vendor/mail` (de componenten) en
`resources/views/vendor/notifications/email.blade.php` (elke melding). Het
thema is `themes/playerpath.css` — de lichte kant uit hoofdstuk 4: gebroken wit
als werkvloer, witte kaart met rand én schaduw, marineblauwe tekst. Vier dingen
die je niet moet omdraaien:

- **De mail draagt de school, niet PlayerPath.** Logo of naam bovenaan, de
  merkkleur op de knop, contactgegevens in de voet, en alleen onderaan de kleine
  regel "Verstuurd met PlayerPath". Een ouder heeft zijn kind bij Keepersschool
  Rob aangemeld; herkent hij de afzender niet, dan opent hij de mail niet, en
  dan is een betaalherinnering of een afgelasting waardeloos.
  **Zonder school** (de platformbeheerder heeft er geen) komt de mail van
  PlayerPath zelf en staat ons logo (`public/brand/logo.png`) bovenaan, met een
  vaste breedte van 180 pixels: Outlook negeert CSS-breedtes op plaatjes. Een
  school zonder eigen logo krijgt dat logo nooit, maar haar naam.
- **De merkkleur gaat inline mee** via `Support\Mail\MailBrand`, want een
  mailprogramma kent geen CSS-variabelen. Die klasse is de enige plek die hem
  uitrekent, met dezelfde contrastcorrectie als de app — een school die
  knalgeel koos krijgt anders een knop met onleesbare letters, en in een mail
  valt dat niet meer te herstellen. **Statuskleuren blijven van PlayerPath**:
  een foutknop is rood, ook bij een school met een eigen kleur.
- **Antwoorden komt bij de school** (`replyTo` op haar contactadres, als ze er
  een heeft). Een ouder die op een betaalmail reageert schrijft aan zijn
  voetbalschool, niet aan een postbus die niemand leest.
- **Mobiel eerst.** Onder 600px vult de kaart het scherm, onder 500px pakt de
  knop de volle breedte; hij is altijd minstens 44 pixels hoog. Wat je niet kunt
  inlinen (de media queries) staat in de `<style>` van `layout.blade.php`, de
  rest komt uit het thema, want een groot deel van de mailprogramma's gooit een
  `<style>` weg.

#### De teksten

- **De kop is de boodschap, niet een begroeting.** "Hallo" op de duurste regel
  van de mail is een weggegooide regel; er staat nu "Sem is ingeschreven" of
  "De betaling is niet gelukt". Dezelfde zin als het onderwerp, zodat een ouder
  na het openen ziet dat hij goed zit.
- **De eerste zin wordt de voorbeeldregel in de inbox** (de preheader). Die
  wordt automatisch afgeleid, zonder opmaaktekens — dus de eerste `line()` van
  een melding moet op zichzelf iets zeggen.
- **Eén knop per mail.** `MailMessage::action()` kán er maar één, en een tweede
  aanroep overschrijft stilletjes de eerste — daar verdween ooit precies de
  betaalknop waar de mail voor bedoeld was. Is er al een knop, dan wordt het
  tweede ding een zin.
- **Toon: rustig en feitelijk.** Geen dreiging over een vergeten incasso, geen
  superlatieven over talent. Wat er gebeurd is, en wat je nu kunt doen.

#### De wachtrij heeft ook een slot nodig

Een mail wordt opgesteld in de **queue-worker**, niet in het verzoek. Daar is
niemand ingelogd, dus de terugval in `AppServiceProvider`
(`auth()->user()?->school`) levert niets op en de global scope staat
fail-closed dicht. Voor de veiligheid precies goed; voor de inhoud precies
fout — een query in `toMail()` geeft dan geen foutmelding maar een leeg
antwoord, en dus een mail waar de helft uit weg is: een inschrijfbevestiging
zonder betaalknop, een uitnodiging die "je kind" zegt in plaats van de naam.

`Support\Tenancy\WithSchool` is daarom voor de wachtrij wat `SetCurrentSchool`
voor een webverzoek is: een job-middleware die de school van de **ontvanger**
zet zolang de melding verwerkt wordt. `SendsFromSchool` hangt hem aan elke
melding; `Uitnodiging` doet het zelf, want daar komt de school uit de
uitnodiging — de ontvanger heeft nog geen account.

- **De school komt van de ontvanger, nooit uit de payload.** Dezelfde regel als
  bij een webverzoek. `NotificationSender` roept `middleware()` per ontvanger
  aan, dus dat kan.
- **Alleen het id reist mee**; de school wordt opgehaald op het moment dat het
  werk draait. Bestaat hij niet meer, dan blijft de scope dicht.
- **Een nieuwe melding met `SendsFromSchool` krijgt dit vanzelf.** Zet je een
  melding op zonder die trait, denk er dan aan — het gaat niet stuk, het wordt
  stil verkeerd.

#### Ze allemaal bekijken

`php artisan mail:preview` schrijft elke mail als HTML-bestand weg (standaard
`storage/app/mail-preview`), met een index. Dat gebeurt in een transactie die
wordt teruggedraaid: er blijft geen verzonnen kind, order of rekening achter.
Meet een wijziging op 375 pixels, net als elk ander scherm.

### De kaart delen als afbeelding

`lib/cardImage.ts` tekent de kaart op een canvas in story-formaat (1080 ×
1920) — niet "gefotografeerd" uit de DOM, want iOS Safari struikelt over
foreignObject en webfonts, en een canvas geeft op elke telefoon dezelfde
scherpe PNG. `deelKaartAlsAfbeelding()` gaat via de Web Share API met een
bestand als dat kan (WhatsApp, Instagram), en anders als download; de
deel-link gaat als tekst mee als hij aanstaat. Op de kaart staat wat de kaart
in de app toont en niets meer. Knoppen: op de kaartpagina ("Deel als
afbeelding" + "Link kopiëren") en de deelknop op het spelerdashboard.

### Snelheid (gemeten, niet aangenomen)

De server is niet het probleem: lokaal duurt elke pagina 9 tot 37 ms, met
11 tot 60 databasevragen (13-9-2026, eigenaar, trainer en ouder). Wat een
gebruiker als traag voelt, zit in de browser en in het wachten op de klik.

- **Het menu laadt vooraf** (`prefetch` bij aanwijzen en aantikken, vijf
  seconden bewaard) in `AppTopbar` en `AppTabbar`. Niet op de meldingen: die
  pagina markeert alles als gelezen zodra hij laadt, en dan zou aanwijzen al
  lezen zijn. `app.ts` gooit de voorraad weg bij elke wijziging (elke visit
  die geen GET is), anders zie je na opslaan nog de oude stand.
- **Het dashboard laadt per rol** (`defineAsyncComponent` in `Dashboard.vue`):
  het sleepraster van de eigenaar, het spelerdashboard en het gezinsdashboard
  komen alleen binnen bij wie ze ziet. Het was één bundel van 200 KB voor
  iedereen.
- **Op productie**: `playerpath:check` waarschuwt als de queue op `sync` staat.
  Dan wacht elke opslag op de mailserver, en dat is precies wat als traag
  voelt.

### De profielfoto en de kaart

Het fotovak op de kaart is **3 bij 2 en toont de bovenkant** (`.pp-foto-vak`,
`object-position: center top`); de opgeslagen foto is vierkant, omdat het
medaillon en de lijsten dat nodig hebben. Van het vierkant valt op de kaart
dus het onderste derde weg. Daarom:

- `PhotoCrop` maakt dat onderste derde donker met "Valt buiten de kaart", en
  het kaartje in het venster heeft hetzelfde fotovak als de echte kaart. Een
  vierkant kaartje liet meer zien dan de kaart straks doet.
- `cardImage.ts` lijnt de foto op de deel-afbeelding ook bovenaan uit. Rond
  het midden snijden gaf een derde, andere uitsnede.

Verander je de verhouding van het fotovak, verander dan alle drie.

### "Zet op je beginscherm"

Iedereen hoort de app op zijn beginscherm te hebben. `composables/useInstall.ts`
is de enige plek die weet wat er kan: de echte knop op Android en desktop
(`beforeinstallprompt`), de twee stappen op iOS (Deel → Zet op beginscherm),
niets als het al een app is (`useAppMode`). `components/InstallSteps.vue`
tekent dat, op de hulppagina en op de kind-link.
`components/InstallPrompt.vue` is de balk onderin: na een halve minuut, ook
bij het eerste bezoek; "niet nu" stelt een week uit, "nee" op de echte knop
een maand (`localStorage`). Niet voorgoed weg: een balk die nooit meer komt
haalt niemand over.

### Help

`/help` (`HelpController` + `pages/Help.vue`): hoe het werkt, per rol, in
de volgorde waarin je het tegenkomt. School: speler → ouder → inschrijven →
rapport en kaart → delen. Ouder: je kind, inschrijven, hoe de kaart groeit,
betalen, delen. Speler: de kaart. Bovenaan overal "zet op je beginscherm".
Onderaan staat **ons telefoonnummer alleen voor de eigenaar**; een ouder,
kind of trainer krijgt "neem contact op met je voetbalschool", met het
telefoonnummer en e-mailadres van die school als ze die heeft. Wij kennen
het kind en de afspraken niet, de school wel.

Een ouderaccount om zelf mee te testen, ook op productie:
`php artisan playerpath:test-ouder {school-slug} --email=… --wachtwoord=…`. Dat
maakt een ouder, gekoppeld aan de best gevulde speler (of een nieuw testkind),
met de kind-link erbij. Kies e-mail en wachtwoord zelf: dan ken je de login
vooraf en hoef je hem niet uit de uitvoer van Forge te halen. Nog eens draaien
met hetzelfde adres zet alleen het wachtwoord opnieuw. De uitvoer is gewone
regels, geen tabel, en zonder school zegt hij dat er eerst een moet komen. Nooit voor een echte
ouder: die krijgt een uitnodiging en kiest zelf een wachtwoord. Het vraagteken in de balk gaat hierheen, voor
iedereen; de rondleiding opnieuw starten staat op deze pagina (eigenaar) en
in het accountmenu. Het menu-item Help staat bij Mijn bedrijf / Mijn account. Het manifest
(`/manifest.webmanifest`) heeft naam, iconen, `start_url` en
`display: standalone`; de test `ProductionReadinessTest` bewaakt dat.

### Seizoenen: de punten horen bij een blok

`Support\Rating\SchoolSeason` (in `schools.rating_settings['season']`):
naam, begin, eind, weken, `closed_at` en `xp_from`. De eigenaar stelt het
in onder Mijn bedrijf → Seizoen (`/seizoen`), met 8/10/12/16 weken als
knoppen en de einddatum die daaruit volgt. Vier regels:

- **XP telt vanaf `xp_from`** (`RatingEngine::recalculate`). De boekingen
  blijven allemaal staan (tijdlijn, historie); alleen de som op de speler is
  seizoensgebonden. Zonder seizoen telt alles, zoals vroeger.
- **Afsluiten** (`Actions\Seasons\CloseSeason`, dagelijks via
  `seasons:close` de dag na de einddatum, of met de hand op de pagina):
  elke speler met iets op zijn kaart krijgt een `PlayerCardSeason` met de
  naam van het seizoen (de eindkaart), `xp_from` wordt de dag erna en de som
  gaat opnieuw. **De rating blijft staan**: die zegt hoe goed iemand is.
  Ouders en speler krijgen `SeizoenAfgesloten` met een knop naar Mijn kaarten.
- **Idempotent**: een dicht seizoen sluit niet nog eens, een eindkaart wordt
  bijgewerkt in plaats van verdubbeld.
- **Levels: brons 0, zilver 251, goud 501, Special 751** (sleutel blijft
  `elite`). Zo is goud binnen één blok haalbaar voor wie trouw komt en
  groeit. `LevelProgress.vue` onder de kaart zegt "Nog 35 XP tot de Gouden
  Kaart!" en heeft de (i) met `CardFaq.vue`: wanneer stijgt mijn kaart,
  wanneer krijg ik een nieuwe, wat gebeurt er aan het eind van het seizoen.

**Delen naar Snapchat** gaat via je foto's (`CardShareActions.vue`): de
afbeelding opslaan, de melding "Kaart opgeslagen in je foto's! Open Snapchat
en kies de foto uit je galerij", en een knop `snapchat://`. Het deelmenu
van de telefoon geeft een afbeelding op veel toestellen niet goed aan
Snapchat door. De deel-afbeelding draagt het logo van de school bovenaan
(publiek niet) en het watermerk "PlayerPath.nl" rechtsonder.

### PWA en productie (Fase 12)

- **De service worker bewaart geen enkel antwoord met gegevens.** Alleen
  `/build/`-assets (hash in de naam, dus onveranderlijk), de iconen en de
  offline-pagina. Het bouwplan zei "geen offline-caching van financiële data";
  dat is hier strenger doorgetrokken, omdat een gedeelde telefoon nooit een
  pagina uit de cache mag teruggeven die niet meer van die gebruiker is.
  POST-verzoeken raakt hij niet aan: een herhaald rapport of een dubbele
  betaling is erger dan geen offline-ondersteuning.
- **Het manifest is dynamisch** (`/manifest.webmanifest`): naam en themakleur
  komen van de school, zodat de app op het beginscherm van een ouder haar naam
  draagt. Het icoon blijft van PlayerPath — een geüpload logo is zelden vierkant.
- Iconen worden getekend door `php artisan playerpath:icons`, niet met de hand,
  zodat ze na een merkwijziging opnieuw uit te draaien zijn.
- `SecurityHeaders` staat op **elk** antwoord. HSTS alleen op productie én over
  https: lokaal zou je browser `playerpath.test` maandenlang naar https dwingen.
- `php artisan playerpath:check` scheidt **blokkerend** van **aandachtspunt**.
  Draai het na elke deploy. Deploystappen die niet in code kunnen staan:
  `DEPLOY.md`.

### Wat het marktonderzoek opleverde (na fase 12)

Het onderzoek (6-9-2026) leidde tot vier wijzigingen die je niet moet terugdraaien:

- **Het admin-groen is verdiept naar `#12813D`** (`143 75% 29%`). Op `42%`
  haalde witte tekst 2,5:1 waar 4,5:1 de norm is; datzelfde groen als tékst op
  de werkvloer haalde 2,7:1. Op 29% klopt het aan beide kanten (4,96:1 en
  4,67:1) en blijft de tint gelijk. Het felle `#22E06B` op de donkere kant
  verandert niet.
- **Cijferknoppen staan onder 480px in twee rijen van vijf.** Tien naast elkaar
  is op een telefoon van 360px nog geen 32px per knop, ruim onder de 44px die
  Apple en WCAG als ondergrens noemen.
- **`Support\Progress\MonthlyDigest` + `players:digest`**: één keer per maand
  naar ouder en speler wat er veranderd is. **Geen bericht zonder inhoud** — een
  maandmail die vier keer "geen nieuws" zegt, leert de ouder hem weg te klikken.
- **`/verantwoording`**: wat de school over een periode kan laten zien aan een
  ouderavond, een vereniging of een gemeente. Steeds meer steden stellen eisen
  aan commerciële voetbalscholen; cijfers over wat er feitelijk is vastgelegd
  zijn daar het tegenargument. Alleen totalen, nooit een kind bij naam.
- **`Support\Dashboard\SetupChecklist`**: vier stappen voor een verse school,
  die **verdwijnen zodra ze gedaan zijn**. Meer dan negentig procent van de
  gebruikers maakt een onboarding nooit af; een handvol stappen halveert de
  uitval bijna ten opzichte van zeven. De eerste is de wizard Inschrijven en
  betalen: alles daarna volgt daaruit.

**Bewust niet gebouwd, en dat blijft zo: ranglijsten tussen spelers.** Onderzoek
naar jeugdsport laat zien dat op beheersing gerichte feedback de motivatie
verhoogt terwijl vergelijkende feedback het ego-gerichte klimaat versterkt, juist
schadelijk bij minder ervaren sporters. Het is bovendien precies waar de sector
publiek op wordt aangesproken.

**Valkuil: een paginaprop met dezelfde naam als een gedeelde prop wint.**
Een pagina die `school` als eigen prop meegeeft (een string) overschrijft de
gedeelde `school` (`{id, name}`) voor die pagina; de rondleiding las daar het
id uit en raakte zo op één scherm zijn stap kwijt. Noem paginaprops nooit
`school`, `auth`, `nav`, `flash`, `features` of `onboarding`.

**Valkuil: `reported_on` heeft een tijdcomponent.** De kolom is een `date`, maar
wordt als `"2026-09-06 00:00:00"` opgeslagen. `whereBetween(...,
[$van->toDateString(), $tot->toDateString()])` sluit een rapport van de laatste
dag dan lexicografisch buiten. Gebruik `whereDate()`.

### Mobiel (gemeten, niet aangenomen)

Het marktonderzoek gaf de richtlijnen; een echte meting op 375px gaf de fouten.
Vier dingen die je niet moet terugdraaien:

- **`min-width: 0` op raster- en flex-items** (`app.css`) en op `SidebarInset`.
  Standaard is dat `auto`, en dan weigert een item onder de min-content-breedte
  van zijn inhoud te krimpen. Eén regel met `truncate` — dat is
  `white-space: nowrap` — maakte de kolom zo breed als die hele regel: het
  dashboard werd 480px op een telefoon van 375, met een horizontale schuifbalk
  over álles heen. Breed materiaal hoort in zijn eigen `overflow-x-auto`.
- **Snelle acties staan boven de cijfers.** De eerste knop stond op y=712 —
  een volledig scherm scrollen voordat je iets kon doen.
- **Kerncijfers staan twee op een rij op mobiel** en zijn daar compacter
  (`p-4`, `text-2xl`, icoon `size-8`). Het dashboard ging van 1788 naar 1312
  pixels: van 2,2 naar 1,6 schermen.
- **Labels breken af, ze kappen niet af.** Met `truncate` werd "Gemiddelde
  rating" op twee kaarten naast elkaar "Gemiddelde r…".

Daarna nog een tweede ronde op een echte telefoon:

- **Rijen met vaste elementen naast een naam stapelen op mobiel.** Bij
  Betalingen stonden bedrag, status en keuzelijst naast de naam; die drie zijn
  `shrink-0`, dus "Sem de Vries" werd "S…". Het patroon is: de rij is
  `flex-col` en wordt `sm:flex-row`, met de vaste elementen in een wrapper die
  op groot scherm `sm:contents` is en dus verdwijnt.
- **Kerncijfers staan overal twee op een rij op mobiel**: dashboard,
  betalingen, verantwoording en de kwartaalsamenvatting op de voortgangspagina.
- **`LineChart` meet zijn eigen breedte** met een ResizeObserver, zodat één
  viewBox-eenheid altijd één beeldpunt is. Met een vaste `width` van 640 in een
  vak van 301 schaalde álles mee met 0,47 en werden de aslabels vier pixels
  hoog — op precies het scherm waarop een ouder kijkt.

**Tikvlakken zijn minstens 44 pixels hoog**, ook tekstlinks als "Bekijk meer"
of "Details": `inline-flex min-h-11 items-center` zonder dat de tekst groter
wordt. Een link van twintig pixels hoog mis je met een duim.

**Filters staan op een telefoon achter één knop** (`components/FilterSheet.vue`):
een knop met een teller voor het aantal actieve filters, en een paneel van
onderen met alle keuzes. Op een groot scherm tekent hetzelfde component de
keuzes gewoon inline (`sm:contents`), met dezelfde v-models — één plek, geen
tweede formulier dat kan gaan afwijken. Het zoekveld blijft altijd in beeld.
Een weergavekeuze (lijst of raster) telt niet mee in het bolletje: dat is geen
filter. Zo werken de agenda, Klanten, Betalingen en de verantwoording.

**Elk scherm dat geen hoofdtabblad is heeft linksboven een weg terug.** Dat
regelt de schil (`AppTopbarLayout`): op een telefoon één knop naar de kruimel
ervoor, en als een pagina maar één kruimel heeft terwijl het adres niet in het
menu staat (de kaart, de meldingen achter het belletje) een stap terug in de
geschiedenis. Geef een subscherm daarom een pad van twee kruimels; dat is
tegelijk het broodkruimelpad op een groot scherm.

**De bevestiging na opslaan is een melding onderin** (`FlashToast` in de
schil, gevoed door `flash.status`). Hij stond bovenaan de pagina, maar de knop
Opslaan staat onderaan en op een telefoon zie je de bovenkant dan niet. Elke
controller die iets opslaat geeft `->with('status', …)` mee; `FlashMessage`
op de pagina's tekent niets meer.

**Meet het zelf na een layoutwijziging** in plaats van te kijken:
`document.documentElement.scrollWidth` hoort gelijk te zijn aan
`clientWidth`. Is hij groter, dan scrollt de pagina zijwaarts. En kijk of een
tekst wordt afgekapt met `scrollWidth > clientWidth` op de tekstelementen
zelf.

### Platformbeheer (super-admin)

De beheeromgeving staat op `/beheer` en is de enige plek waar iemand over
scholen heen kijkt. Vier eigenschappen die je niet moet weghalen:

1. **Eén plek waar de scope opengaat.** `SchoolScope` filtert niet bij
   `isDisabled()` (een tijdelijke uitzondering rond één blok code) of bij
   `isPlatform()` (de beheeromgeving). Er is geen derde, en er staan nergens
   losse uitzonderingen in controllers.
2. **Openzetten en rolcontrole staan in dezelfde middleware.**
   `EnterPlatform` doet eerst de rolcontrole en pas daarna
   `Tenancy::enterPlatform()`. Zolang die twee bij elkaar staan kan er geen
   route bestaan die het één wel doet en het ander niet.
3. **De platformmodus wordt weer dichtgezet** in `terminate()`. In een gewone
   webrequest maakt dat niets uit, maar in een langlevend proces (tests,
   Octane) zou de scope open blijven staan voor het volgende stuk werk. Er
   staat een test op die precies dat controleert.
4. **Een platformbeheerder heeft geen school.** In de gewone app is de scope
   voor hem dus fail-closed: hij ziet niets. `/dashboard` stuurt hem door naar
   `/beheer`. Eén school bekijken gaat straks via impersonatie, en dán heeft
   hij wél een school.

Wie er niet hoort krijgt een **404 en geen 403**: dat de beheeromgeving bestaat
is niets wat een schooleigenaar hoeft te weten.

Een beheerder maak je met `php artisan platform:create-admin`. Bewust alleen
via de commandoregel: een scherm waarmee iemand zichzelf boven alle scholen kan
zetten hoort niet te bestaan.

### Functies per school

`App\Enums\Feature` is de enige lijst met featurenamen. Een feature toevoegen
is één case erbij; het beheerscherm, de opslag, de middleware, het menu en de
geplande taken lezen allemaal die enum. **Hernoem een sleutel nooit** zonder
migratie: hij staat opgeslagen in `schools.features`, en een school die iets
had uitgezet zou het stilzwijgend terugkrijgen.

Drie regels:

- **Onbekend betekent aan.** Een nieuwe feature staat bij bestaande scholen aan;
  andersom zouden scholen zonder het te weten iets kwijtraken.
- **Uit is echt dicht.** `RequireFeature` (`'feature:kalender'` op een route)
  geeft een 404. Het menu weglaten is cosmetica; wie de URL intypt moet
  stuklopen.
- **Ook de achtergrondtaken stoppen.** `payments:generate`, `payments:collect`,
  `payments:remind` en `players:digest` slaan een school over waar de functie
  uitstaat. Anders lopen er rekeningen door bij een school die betalingen niet
  heeft.

### Bekijken als (impersonatie)

Onmisbaar voor support en tegelijk het gevoeligste dat er in het product zit.
Vier voorwaarden, geen ervan optioneel: alleen de platformbeheerder start het,
nooit als een andere platformbeheerder, alles wordt vastgelegd in
`impersonations` (wie, bij wie, welke school, wanneer, vanaf welk adres,
wanneer gestopt), en er staat een niet-weg-te-klikken balk bovenaan met een
knop terug.

**De uitgang staat buiten `/beheer`** (`POST /stop-bekijken`). `EnterPlatform`
weigert verzoeken zolang je aan het kijken bent; zat de uitgang binnen die
groep, dan lag hij achter de deur die hij zelf op slot doet.

In `EnterPlatform` staat de impersonatiecontrole **vóór** de rolcontrole.
Andersom gaat hij nooit af — tijdens het kijken ben je de schoolgebruiker en
val je al op de rol af, met een 404 die niets uitlegt.

### Pakketten

`App\Enums\Package` (Start / Ontwikkeling / Academie) is een set functies met
een prijs eraan. Het kiezen van een pakket zet `schools.features` in één keer
goed; **daarna dwingt het niets meer af**. Per school kun je losse functies aan-
of uitzetten, en het detailscherm zegt dan "wijkt af van het pakket".

Twee dingen die daaruit volgen en die je niet moet omdraaien:

- **Alleen een echte pakketwissel zet de functies opnieuw.** Zou elke opslag dat
  doen, dan draai je stilzwijgend een afwijking terug die je bewust hebt gemaakt.
- **De prijs is er om te tonen, niet om te factureren.** PlayerPath stuurt
  zichzelf geen rekeningen; de bedragen komen uit het marktonderzoek.

### Logboek

Elke beheeractie gaat via `Support\Platform\PlatformAudit` naar `platform_logs`.
Eén plek, zodat geen actie "toevallig" niet gelogd wordt.

- De **samenvatting is een leesbare zin**, geen JSON. Over een jaar wil je
  "Kalender uitgezet" lezen, niet `{"features":{"kalender":false}}` ontcijferen.
  De ruwe details staan er los bij.
- **De naam van de school staat er als tekst bij.** `school_id` is nullOnDelete,
  dus juist de regel van een verwijderde school moet leesbaar blijven — dat is
  precies de regel die je later zoekt.
- Een opslag **zonder wijziging logt niets**. Een logboek vol lege regels lees
  je niet meer.

`platform_logs` staat los van `impersonations`: dat zijn sessies met een begin
en een eind, dit zijn losse gebeurtenissen op één moment.

### Een school opzeggen

Twee verschillende dingen, en die moet je niet door elkaar halen:

- **Uitzetten** (`is_active = false`) is omkeerbaar: niemand kan meer inloggen,
  alle gegevens blijven staan.
- **Verwijderen** (`Actions\Platform\DeleteSchool`) is definitief. Dat is het
  einde van een klantrelatie: de gegevens gaan weg en er valt niets te herstellen.

De bevestiging vraagt om **de naam van de school, letterlijk overgetypt**. Een
`confirm()`-venster klik je weg zonder te lezen; een naam overtypen doe je niet
per ongeluk.

De databasesleutels doen het meeste werk — elke tabel met `school_id` staat op
`cascadeOnDelete`. Drie dingen hebben geen `school_id` en worden met de hand
opgeruimd: **meldingen** (polymorf aan de gebruiker), **sessies** (zodat een
ingelogde trainer er meteen uit ligt in plaats van bij zijn volgende klik) en
**wachtwoord-reset-tokens** (een oude link mag geen account meer herstellen).

Valkuil: in de beheeromgeving staat de scope **open**, dus `Player::count()`
telt daar álle scholen. `DeleteSchool::summarise()` zet de school daarom met de
hand in de query (`withoutSchoolScope()->where('school_id', ...)`) — anders
staat er een veel te groot getal in de bevestiging.

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
- [x] Fase 8 — Inzageverzoek per speler; bewaartermijn en ondertekenen geschrapt
- [x] Fase 9 — Betalingen (Mollie): eenmalig, doorlopende incasso, termijnen, herinneringen
- [x] Fase 10 — Communicatie (mededelingen, afzeggen, meldingsvoorkeuren)
- [x] Fase 11 — White-label & subdomein (logo, merkkleur, branding per adres)
- [~] Fase 12 — Productie & lancering: PWA en hardening staan; deploy en push nog niet

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
