# Bouwplan — Keeper-/voetbalschool platform met Claude Code

Gefaseerd stappenplan om het systeem te bouwen in **Laravel + Inertia + Vue**,
op dezelfde manier als je B-Organized aanpakt: met Claude Code, een CLAUDE.md
als kompas, en fase voor fase. **Betalingen (Mollie) komen bewust pas laat** —
je bouwt eerst een compleet werkend product en zet daarna pas het gevoeligste
stuk erop.

De volledige feature-scope (must-have, ontwikkelingslaag, later) staat in
`FEATURES.md`. Dit bouwplan is de volgorde waarin die scope gebouwd wordt.

## Werkwijze (zo gebruik je dit plan)

- Werk **één fase tegelijk** af met Claude Code. Begin een fase niet voordat de
  vorige werkt en getest is.
- Na elke fase: **klikken en testen**. Werkt het? Pas dan door.
- Houd de brokken klein. Vraag Claude Code per fase om een concreet, afgebakend
  stuk — niet "bouw de hele app".
- Commit na elke werkende stap (git), zodat je altijd terug kunt.

---

## CLAUDE.md — schrijf dit eerst ✅

Staat in de root. Bevat stack, harde regels (multi-tenancy server-side, geld in
centen, alles Nederlands), huisstijl (licht voor beheer, donker voor de
spelerskant), datamodel en de valkuilen die onderweg gevonden zijn.

---

## Fase 0 — Projectopzet & fundament ✅

Laravel Vue starter kit, Herd + SQLite, Fortify, git, leeg dashboard met navigatie.

## Fase 1 — Datamodel & multi-tenancy ✅

School, User, Speler, Groep, ouder-koppeling. `school_id` op elke tabel, één
centrale scope-laag (fail-closed), rollen met policies. Bewezen met tests dat
school A niets van school B ziet.

## Fase 2 — Het kloppend hart: rapport → spelerskaart ✅

Rapport in ~30 seconden (voorgevuld, één tik per cijfer, cijfertoetsen),
keeper- en veldspelercategorieën, doorrekening naar overall + sub-scores.

## Fase 3 — Spelers- & groepsbeheer ✅

Spelers toevoegen/bewerken, groepen, ouders koppelen en uitnodigen. Later
uitgebreid tot **Gebruikers** (spelers, trainers, ouders in één scherm).

## Fase 4 — Planning & aanwezigheid ✅

Trainingen (wekelijks herhalen, meerdere trainers, locatie), kalender maand/week,
afvinken door trainer, aan-/afmelden door ouder/speler.

## Fase 5 — Voortgang & ouder-ervaring ✅

Groeigrafieken, tijdlijn, meldingen (app + mail via queue), kaart met niveau,
badges en publieke deel-link. Later: de kaart als verzamelkaart.

## Fase 6 — Eigenaar-dashboard ✅

Kerncijfers, aandachtslijst, komende trainingen, financieel vak, snelle acties.
Plus: overzichten met export (spelers, trainingen, aanwezigheid, financieel
werkboek) en online inschrijven met goedkeuring door de eigenaar.

---

## Fase 7 — Ontwikkelingsdoelen (O2)

**Doel:** het onderscheid verstevigen vóór de commerciële features. Een trainer
stelt per speler meetbare doelen, en ouder en speler zien de weg ernaartoe.

- Doel per speler: categorie, streefcijfer, periode (bijv. "Uitkomen naar 80
  vóór 1 december"), gesteld door de trainer, zichtbaar voor ouder en speler.
- Voortgang t.o.v. het doel op de kaart, de voortgangspagina en in de tijdlijn;
  melding en badge bij behalen.
- Doelen meenemen in het rapport-invulscherm zonder het ritme van 30 seconden
  te breken (een klein "op koers / niet op koers" naast de categorie).
- Overzicht voor de eigenaar: hoeveel spelers hebben een actief doel.

**Klaar wanneer:** een trainer zet een doel, vult een rapport in, en de ouder
ziet de groei richting het doel — met een mijlpaal als het gehaald is.

## Fase 8 — Inschrijven compleet & digitaal ondertekenen (M8, rest van M1)

**Doel:** de inschrijving juridisch en praktisch compleet, zonder dat daar al
geld voor hoeft te lopen.

- Documenten per school (toestemming, AVG/privacy, gedragscode), met versies.
- Digitaal ondertekenen in het inschrijfformulier: naam, tijdstip, IP,
  documentversie; opslag bij de inschrijving en later bij de speler.
- Opnieuw laten tekenen als een document een nieuwe versie krijgt; overzicht
  voor de eigenaar van wie nog niet getekend heeft.
- Bewaartermijn en verwijderen volgens AVG (verzoek van ouder → export en
  wissen).

**Klaar wanneer:** een ouder schrijft in, tekent de documenten, en de eigenaar
ziet bij goedkeuring precies wat er getekend is en wanneer.

## Fase 9 — Betalingen (Mollie + Cashier) (M2)

**Doel:** het gevoeligste onderdeel, bewust op een product dat verder al werkt.

- Mollie-account + Laravel Cashier (Mollie); `MollieGateway` achter de bestaande
  `PaymentGateway`-naad.
- Drie betaalmodellen: **abonnement** (maandelijkse SEPA-incasso), **eenmalig**
  (iDEAL) en **termijnen** (een bedrag in N delen; nieuw in het model).
- Betaalstart vanuit de inschrijving: gezin kiest plan, na goedkeuring start de
  eerste betaling of het mandaat automatisch.
- Webhooks, storneringen, mislukte incasso's en **automatische herinneringen**
  (dit doen alle concurrenten; hier moeten we mee).
- Betalingsoverzicht in de exports met echte incassodata; financieel dashboard
  afmaken.

**Klaar wanneer:** een ouder schrijft in, betaalt via iDEAL of incasso, en de
eigenaar ziet de betaling terug. Test dit grondig — dit is waar fouten het
meest pijn doen.

## Fase 10 — Communicatie (M6)

**Doel:** de school bereikt ouders zonder WhatsApp-groepen.

- Mededelingen van school naar alle ouders of naar een groep (afgelasting,
  nieuws, oproep), in de app en per e-mail via de queue.
- Berichteninbox voor ouders en spelers; meldingsvoorkeuren.
- Voorbereiding op push (komt met de PWA in fase 12).

**Klaar wanneer:** de eigenaar zegt een training af en elke ouder van die groep
weet het binnen een minuut.

## Fase 11 — White-label & subdomein (M10)

**Doel:** elke school voelt de app als de zijne.

- Logo en kleuren per school (instelling, geen aparte versie), zichtbaar in de
  app, op de kaart, in het inschrijfformulier en in e-mails.
- Eigen subdomein per school (`xtra.playerpath.nl`). Let op: het subdomein
  bepaalt de **branding en de inlogpagina**, nooit de dataschieding — die blijft
  uit het ingelogde account komen (CLAUDE.md 3.1).
- E-mails uit naam van de school.

**Klaar wanneer:** twee scholen draaien naast elkaar met eigen logo, kleuren en
adres, en zien nog steeds niets van elkaar.

## Fase 12 — Productie & lancering (M7 + deploy)

**Doel:** live en klaar voor de eerste school.

- **PWA-laag** (vite-plugin-pwa): installeerbaar op mobiel met app-gevoel voor
  trainers én ouders/spelers; push-meldingen. Bewust geen offline-caching van
  financiële data.
- Deploy naar **Hetzner via Laravel Forge**: MySQL + Redis, object storage (EU),
  mail via Brevo, queue-worker onder supervisor.
- Security-hardening, backups, e-mailverificatie aan.
- Onboarding-flow: hoe zet je een nieuwe school (Rob en Yoel als eerste) erin.

**Klaar wanneer:** de eerste school kan er echt mee werken.

---

## Later (niet in een fase)

Video-analyse, oefeningenbibliotheek, tryout-workflows, scouting, leaderboards
tussen scholen. Pas als de eerste scholen live draaien en erom vragen.

---

## Principes om vast te houden

- **Fundament vóór schermen.** Fase 0 en 1 bepalen alles daarna.
- **Bouw alleen wat elke school kan gebruiken.** School-specifieke wensen worden
  een instelling (aan/uit), geen aparte versie.
- **Test na elke fase.** Kleine, werkende stappen kloppen bijna altijd; grote
  sprongen breken.
- **De rapport-invoer is je belangrijkste UX.** Als dat niet in ~30 seconden
  kan, blijven de kaarten leeg en stort je onderscheid in.
- **Betalingen laat en apart.** Eerst een compleet werkend product, dan pas het
  geld erop.
