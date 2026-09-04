# Bouwplan — Keeper-/voetbalschool platform met Claude Code

Gefaseerd stappenplan om het systeem te bouwen in **Laravel + Inertia + Vue**,
op dezelfde manier als je B-Organized aanpakt: met Claude Code, een CLAUDE.md
als kompas, en fase voor fase. **Betalingen (Mollie) komen bewust pas aan het
eind** — je bouwt eerst een compleet werkend product en zet daarna pas het
gevoeligste stuk erop.

## Werkwijze (zo gebruik je dit plan)

- Werk **één fase tegelijk** af met Claude Code. Begin een fase niet voordat de
  vorige werkt en getest is.
- Na elke fase: **klikken en testen**. Werkt het? Pas dan door.
- Houd de brokken klein. Vraag Claude Code per fase om een concreet, afgebakend
  stuk — niet "bouw de hele app".
- Commit na elke werkende stap (git), zodat je altijd terug kunt.

---

## CLAUDE.md — schrijf dit eerst

Voordat je begint met bouwen, zet je een `CLAUDE.md` in de root met de regels
waar Claude Code zich aan moet houden. Neem hierin op:

- **Wat het is:** SaaS-platform voor keeper- en voetbalscholen. Meerdere scholen
  (tenants) in één systeem, elk met eigen data.
- **Stack:** Laravel + Inertia + Vue + Tailwind. Fortify voor auth,
  spatie/laravel-permission voor rollen, PrimeVue voor admin-UI, Mollie +
  Laravel Cashier voor betalingen (pas in de laatste fase).
- **Multi-tenancy (harde regel):** elke tabel met klantdata krijgt een
  `school_id`. Scoping gebeurt **altijd server-side** via één centrale scope-laag
  (global scope + middleware) — nooit alleen in de UI verbergen. Een school mag
  nooit data van een andere school kunnen zien.
- **Rollen:** eigenaar, trainer, ouder, speler.
- **Geld:** altijd als integers in centen (of decimal), **nooit als float**.
- **Taal:** alles volledig in het Nederlands — UI, e-mails, validatie- en
  foutmeldingen.
- **Huisstijl:** leg je kleuren, font en afrondingen vast (zoals je bij
  B-Organized deed), zodat de UI consistent blijft.

---

## Fase 0 — Projectopzet & fundament

**Doel:** een lege maar draaiende app met inloggen.

- Scaffolden met de **officiële Laravel Vue starter kit** (Laravel + Inertia +
  Vue + Tailwind + Vite).
- Lokaal draaien met **Laravel Herd + SQLite** (zero-config; MySQL pas voor
  productie).
- Git repo opzetten, eerste commit.
- **Fortify** voor auth (inloggen, e-mailverificatie; zelfregistratie later
  bepalen).
- Basis-layout: een leeg dashboard-skelet met navigatie.

**Klaar wanneer:** je kunt inloggen en een leeg dashboard zien.

## Fase 1 — Datamodel & multi-tenancy (de ruggengraat)

**Doel:** de datastructuur en de waterdichte scheiding tussen scholen. Dit is
het belangrijkste fundament — hier niet haasten.

- Kern-entiteiten en relaties:
  - **School** (de tenant)
  - **User** (hoort bij één school, heeft een rol)
  - **Speler** (hoort bij een school; keeper of veldspeler; leeftijdscategorie)
  - **Groep** (trainingsgroep / leeftijdscategorie)
  - **Ouder-koppeling** (welke ouder hoort bij welke speler)
  - *(Rapport, Training, Betaling komen in latere fases erbij)*
- **Multi-tenancy:** `school_id` op elke tabel, plus een centrale scope-laag die
  alles automatisch filtert op de ingelogde school. Server-side, één plek.
- **Rollen** met spatie/laravel-permission: eigenaar, trainer, ouder, speler +
  policies die bepalen wie wat mag.
- Migrations, models, relaties en policies.

**Klaar wanneer:** je kunt (als test) twee scholen aanmaken en bewijzen dat de
één de data van de ander niet kan zien.

## Fase 2 — Het kloppend hart: rapport → spelerskaart

**Doel:** het onderscheidende deel als eerste werkend. Eén compleet spoor van
begin tot eind.

- **Rapport invullen** (trainer): het 30-seconden-scherm met scores per
  categorie. Keeper-categorieën (reflexen, uitkomen, voetenwerk, 1-op-1, hoge
  ballen, communicatie) vs veldspeler-categorieën.
- **Doorrekenen naar de spelerskaart:** overall rating + sub-scores op basis van
  de rapporten.
- **Spelerskaart tonen** (speler/ouder): de FIFA-kaart met de actuele cijfers.

**Klaar wanneer:** een trainer vult een rapport in en de spelerskaart verandert
zichtbaar mee. Dit is de kern van je product — als dit staat, staat het hart.

## Fase 3 — Spelers- & groepsbeheer

**Doel:** de school kan zijn spelers beheren.

- Spelers toevoegen/bewerken, indelen in groepen en leeftijdscategorieën.
- Spelersoverzicht (tabel, filterbaar) + spelersdetail.
- Ouders koppelen aan hun kind(eren).

**Klaar wanneer:** een eigenaar kan zijn hele ledenbestand opzetten en beheren.

## Fase 4 — Planning & aanwezigheid

**Doel:** trainingen organiseren.

- Trainingen inplannen (agenda/rooster), gekoppeld aan groepen.
- Aanwezigheid afvinken per training.
- Speler/ouder ziet komende trainingen en kan zich aan-/afmelden.

**Klaar wanneer:** je kunt een training plannen, de groep ziet 'm, en
aanwezigheid werkt.

## Fase 5 — Voortgang & ouder-ervaring

**Doel:** de features die klanten vasthouden (je onderscheiders 2 en 3).

- **Voortgang:** grafiek van de groei per categorie over de tijd.
- **Ouder-tijdlijn:** feed met nieuwe rapporten, highlights, kwartaal-terugblik.
- **Meldingen:** in-app + e-mail (via Brevo) bij een nieuw rapport e.d.
  Op de achtergrond via **queues (Redis)**.
- **FIFA-kaart aankleden:** seizoen/level, mijlpalen, badges, deel-functie.

**Klaar wanneer:** een ouder krijgt een melding bij een nieuw rapport, ziet de
groei van z'n kind, en de kaart voelt levend.

## Fase 6 — Eigenaar-dashboard

**Doel:** overzicht voor de schooleigenaar.

- Kerncijfers (aantal spelers, komende trainingen, later: omzet).
- Overzichten en snelle acties.
- Voorbereiding van het financiële overzicht (de echte betaalcijfers komen in
  fase 7).

**Klaar wanneer:** de eigenaar heeft één scherm met grip op zijn school.

## Fase 7 — Betalingen (Mollie + Cashier) — het laatste, aparte stuk

**Doel:** het gevoeligste onderdeel, bewust als laatste, op een al werkend
product.

- **Mollie** account + **Laravel Cashier (Mollie)** installeren.
- Inschrijven mét betalen: **iDEAL** (eenmalig) en **SEPA-incasso** (maandelijks
  abonnement).
- Abonnementen per ouder/speler beheren; storneringen afhandelen.
- Betaaloverzicht, automatische herinneringen, en het financiële dashboard
  afmaken.

**Klaar wanneer:** een ouder schrijft in, betaalt via iDEAL of incasso, en de
eigenaar ziet de betaling terug. Test dit grondig — dit is waar fouten het
meest pijn doen.

## Fase 8 — Productie & lancering

**Doel:** live en klaar voor je eerste school.

- **PWA-laag** (vite-plugin-pwa): installeerbaar op mobiel met app-gevoel voor
  spelers/ouders. Bewust geen offline-caching van financiële data.
- Deploy naar **Hetzner via Laravel Forge**: MySQL + Redis, object storage (EU),
  mail via Brevo.
- Security-hardening, backups, e-mailverificatie aan.
- Onboarding-flow: hoe zet je een nieuwe school (Rob en Yoel als eerste) erin.

**Klaar wanneer:** de eerste school kan er echt mee werken.

---

## Principes om vast te houden

- **Fundament vóór schermen.** Fase 0 en 1 (datamodel + multi-tenancy) bepalen
  alles daarna. Daar niet haasten.
- **Bouw alleen wat elke school kan gebruiken.** School-specifieke wensen worden
  een instelling (aan/uit), geen aparte versie.
- **Test na elke fase.** Kleine, werkende stappen kloppen bijna altijd; grote
  sprongen breken.
- **De rapport-invoer is je belangrijkste UX.** Als dat niet in ~30 seconden
  kan, blijven de kaarten leeg en stort je onderscheid in. Fase 2 is niet "af"
  tot dat scherm echt snel is.
- **Betalingen laatst en apart.** Precies zoals je nu plant — eerst een compleet
  werkend product, dan pas het geld erop.
