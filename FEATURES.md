# PlayerPath — feature-scope

Op basis van marktonderzoek (Trainin, PlayerHQ, 360Player, SportsEngine, eSoft,
SDMS) en eigen vergelijking (ClubCollect, VreugdOnline, Waresport). Drie niveaus:
wat je nodig hebt om mee te doen, waar we beter in moeten zijn, en wat later komt.

Status: ✅ klaar · 🟡 deels · ⬜ nog niet. De fase verwijst naar
`bouwplan-keepersplatform-claude-code.md`.

## MUST-HAVE — table stakes

| # | Feature | Status | Wat er staat / ontbreekt | Fase |
|---|---|---|---|---|
| M1 | Online inschrijving door ouders/spelers | 🟡 | Openbaar formulier per school (`/inschrijven/{slug}`), tariefkeuze, inbox met goedkeuring die speler, ouderaccount en abonnement aanmaakt. Ontbreekt: ondertekenen (M8) en betaalstart (M2). | 0–6 ✅ · rest in 8 en 9 |
| M2 | Betalingen: abonnement (maandelijkse incasso) + eenmalig (iDEAL) + termijnen; gezin kiest plan, systeem int automatisch | 🟡 | Tarieven, abonnementen, betalingen en het financiële dashboard staan; bedragen in centen; `PaymentGateway`-naad. Ontbreekt: Mollie zelf, incasso, iDEAL-checkout, webhooks, storno's, herinneringen, en **termijnen** (bestaat nog niet in het model). | 9 |
| M3 | Agenda/planning van trainingen met trainer(s) en locatie | ✅ | Trainingen (wekelijks herhalen), trainers many-to-many, locatie, kalender maand/week, mobiel. | 4 ✅ |
| M4 | Aanwezigheidsregistratie | ✅ | Afvinken door trainer, aan-/afmelden door ouder/speler, gescheiden vastgelegd, opkomst op dashboard. | 4 ✅ |
| M5 | Ledenadministratie (spelers, ouders, trainers) met rollen | ✅ | Gebruikers met drie tabbladen, uitnodigen via e-mail, rollen eigenaar/trainer/ouder/speler, policies, multi-tenancy server-side. | 1, 3 ✅ |
| M6 | In-app communicatie + meldingen naar ouders | 🟡 | Meldingen (nieuw rapport, nieuwe inschrijving) in app en mail via queue. Ontbreekt: **mededelingen van school naar ouders/groep** (afgelasting, nieuws), inbox/berichten, later push. | 10 |
| M7 | Mobiele toegang / app-gevoel (PWA) voor trainers én ouders/spelers | 🟡 | Alle schermen mobiel-first gebouwd. Ontbreekt: installeerbare PWA (manifest, service worker, icoon, push). | 12 |
| M8 | Digitaal ondertekenen van formulieren bij inschrijving (toestemming, AVG, gedrag) | ⬜ | Alleen een akkoord-vinkje op het inschrijfformulier. Ontbreekt: documenten per school, handtekening (getekend + tijdstip + IP + versie), bewaren, opnieuw laten tekenen bij nieuwe versie. | 8 |
| M9 | Overzichten met export (CSV/Excel): leden, aanwezigheid, later betalingen | ✅ | Spelers, Trainingen, Aanwezigheid en een financieel werkboek met vier tabbladen. Uitbreidbaar via `ExportRegistry`. Echte incassodata volgt met M2. | 6 ✅ (betaaldata in 9) |
| M10 | White-label branding per school (logo, kleuren) + eigen subdomein (`xtra.playerpath.nl`) | ⬜ | Eén huisstijl voor alles. Ontbreekt: logo en kleuren per school, subdomein per school, e-mails uit naam van de school. | 11 |

## ONTWIKKELINGSLAAG — het onderscheid

| # | Feature | Status | Wat er staat / ontbreekt | Fase |
|---|---|---|---|---|
| O1 | Coach-rapporten/assessments per speler, ~30 sec invulbaar | ✅ | Zes categorieën per positie, voorgevuld met vorig rapport, één tik per cijfer, cijfertoetsen, 44px-knoppen. | 2 ✅ |
| O2 | Meetbare ontwikkelingsdoelen per speler per periode | ⬜ | Bestaat niet. Nodig: doel per speler (categorie, streefcijfer, periode), voortgang t.o.v. doel op kaart en tijdlijn, trainer stelt op, ouder ziet. | 7 |
| O3 | Spelerprofiel dat over tijd opbouwt | ✅ | Rapporthistorie, voortgangsgrafieken per categorie, kwartaal-terugblik. | 5 ✅ |
| O4 | Spelerskaart met gamification: evoluerend, badges, mijlpalen, deelbaar | ✅ | Verzamelkaart met niveau (brons→elite), badges, keeper-/veldspelerlook, publieke deel-link met privacybegrenzing. Wordt in fase 7 uitgebreid met doelen. | 5 ✅ |
| O5 | Keeper-specifieke categorieën naast veldspeler-categorieën | ✅ | `ReportCategory` per positie. | 2 ✅ |
| O6 | Ouder-tijdlijn die groei zichtbaar maakt | ✅ | Tijdlijn met rapporten en mijlpalen, melding bij nieuw rapport, kaart op het ouderdashboard. Krijgt in fase 7 doelen erbij. | 5 ✅ |

## LATER — niet nu bouwen

- Video-analyse
- Oefeningenbibliotheek
- Tryout-workflows
- Scouting
- Leaderboards tussen scholen

Deze staan bewust niet in een fase. Ze komen pas aan bod als de eerste scholen
live draaien en erom vragen.

## Wat er nog te doen is, op volgorde

| Fase | Naam | Features |
|---|---|---|
| 7 | Ontwikkelingsdoelen | O2 |
| 8 | Inschrijven compleet & ondertekenen | M8, rest van M1 |
| 9 | Betalingen (Mollie) | M2, betaaldata in M9 |
| 10 | Communicatie | M6 |
| 11 | White-label & subdomein | M10 |
| 12 | Productie & lancering | M7 (PWA), deploy, hardening |
