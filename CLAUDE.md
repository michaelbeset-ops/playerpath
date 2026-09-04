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

- **Elke tabel met klantdata krijgt een `school_id`.** Geen uitzonderingen.
- Scoping gebeurt **altijd server-side**, via **één centrale scope-laag**
  (een global scope op de models + middleware die de actieve school bepaalt).
  Niet per controller opnieuw, niet per query handmatig.
- **Nooit** data verbergen in de UI en denken dat het daarmee beveiligd is.
- Een school mag **nooit** data van een andere school kunnen zien, lezen,
  raden via een ID in de URL, of via een API-response terugkrijgen.
- Bij elk nieuw model: `school_id` + de global scope + een policy. Alle drie.

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

Autorisatie loopt via **policies**, niet via `if ($user->role === ...)` in views.

---

## 4. Huisstijl

Donkere, premium basis — bewust in de geest van een FIFA-kaart: cijfers en
metallic accenten springen eruit op donker. Fel groen verwijst naar het veld en
staat voor groei en energie.

### Kleuren

| Rol | Kleur | Hex |
|---|---|---|
| Basis / achtergrond | Bijna-zwart donkerblauw | `#0A0F1C` |
| Surface (kaarten, panelen) | Donkerblauw | `#111A2E` |
| Surface hoog / randen | Donkerblauw licht | `#1C2942` |
| Primair / actie | Fel groen | `#22E06B` |
| Primair hover | Groen donker | `#16B85A` |
| Accent (kaart, badges, mijlpalen) | Goud/metallic | `#D4AF37` |
| Tekst primair | Bijna-wit | `#F1F5F9` |
| Tekst secundair | Grijsblauw | `#94A3B8` |
| Succes / waarschuwing / fout | `#22E06B` / `#F59E0B` / `#EF4444` |

- **Donker is de standaard**, niet een thema-optie.
- Goud is **schaars**: alleen voor de spelerskaart, badges en mijlpalen.
  Niet voor gewone knoppen.

### Typografie

- Font: **Inter** (fallback: system-ui, sans-serif).
- Cijfers op de spelerskaart: zwaar (700–800), strak, `tabular-nums`.

### Vorm

- Afronding: `rounded-xl` (12px) standaard, `rounded-2xl` (16px) voor de
  spelerskaart en grote panelen, `rounded-lg` (8px) voor inputs.
- Randen: 1px `#1C2942`. Schaduwen subtiel; diepte komt van kleurverschil,
  niet van harde schaduwen.
- Ruim gebruik van witruimte (spacing-schaal 4/6/8).

---

## 5. Werkwijze

- **Fase voor fase.** Het bouwplan staat in `bouwplan-keepersplatform-claude-code.md`
  (fase 0 t/m 8). Begin een fase pas als de vorige werkt en getest is.
- **Nooit vooruitlopen.** Bouw alleen wat in de huidige fase staat.
- Kleine, werkende stappen. Na elke werkende stap: **git commit**.
- Bij onduidelijkheid of dubbelzinnigheid: **eerst vragen, niet gokken**.
- Na afronding van een fase: samenvatten hoe het getest kan worden, en
  **wachten op akkoord** voor de volgende fase.

### Fase-status

- [x] Fase 0 — Projectopzet & fundament
- [ ] Fase 1 — Datamodel & multi-tenancy
- [ ] Fase 2 — Rapport → spelerskaart
- [ ] Fase 3 — Spelers- & groepsbeheer
- [ ] Fase 4 — Planning & aanwezigheid
- [ ] Fase 5 — Voortgang & ouder-ervaring
- [ ] Fase 6 — Eigenaar-dashboard
- [ ] Fase 7 — Betalingen (Mollie + Cashier)
- [ ] Fase 8 — Productie & lancering

---

## 6. Principes

- **Fundament vóór schermen.** Fase 0 en 1 bepalen alles daarna.
- **Bouw alleen wat elke school kan gebruiken.** School-specifieke wensen worden
  een instelling (aan/uit), nooit een aparte versie of een `if ($school->id === 3)`.
- **De rapport-invoer is de belangrijkste UX.** Kan een trainer het niet in
  ~30 seconden, dan blijven de kaarten leeg en stort het product in.
- **Betalingen laatst en apart.** Geen Mollie/Cashier-code vóór fase 7.

---

## 7. Praktisch (lokaal)

- PHP/Composer komen van Herd; draai die commando's via **PowerShell**, niet via bash.
- Database: **SQLite** (`database/database.sqlite`). Geen MySQL lokaal.
- Server: Herd serveert de map automatisch. Assets: `npm run dev`.
- Migraties: `php artisan migrate`. Verse start: `php artisan migrate:fresh --seed`.
