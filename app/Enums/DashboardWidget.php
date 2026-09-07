<?php

namespace App\Enums;

/**
 * De onderdelen waaruit het dashboard is opgebouwd.
 *
 * Dit is de enige lijst. De standaardindeling, het scherm en straks de
 * bewerkmodus lezen allemaal deze enum, dus een widget erbij is één case plus
 * één Vue-component.
 *
 * **Hernoem een waarde nooit** zonder migratie: hij staat opgeslagen in
 * `users.dashboard_layout`, en iemand die een widget bewust had weggehaald zou
 * hem stilzwijgend terugkrijgen.
 *
 * Het aandacht-blok staat er bewust **niet** in. Dat hoort altijd bovenaan en
 * is niet weg te halen; zou het een widget zijn, dan is "vastgepind" een regel
 * die iemand kan omzeilen zodra er ooit een knop bijkomt.
 */
enum DashboardWidget: string
{
    case KpiPlayers = 'kpi_players';
    case KpiRating = 'kpi_rating';
    case KpiReports = 'kpi_reports';
    case KpiRevenue = 'kpi_revenue';
    case Development = 'development';
    case Finance = 'finance';
    case Trainings = 'trainings';
    case Birthdays = 'birthdays';
    // Van de trainer: waar moet ik zijn, en wie moet ik nog beoordelen.
    case MyTrainings = 'my_trainings';
    case MyPlayers = 'my_players';

    public function label(): string
    {
        return match ($this) {
            self::KpiPlayers => 'Actieve spelers',
            self::KpiRating => 'Gemiddelde rating',
            self::KpiReports => 'Rapporten deze week',
            self::KpiRevenue => 'Omzet deze maand',
            self::Development => 'Ontwikkeling',
            self::Finance => 'Financieel',
            self::Trainings => 'Komende trainingen',
            self::Birthdays => 'Verjaardagen',
            self::MyTrainings => 'Mijn trainingen',
            self::MyPlayers => 'Mijn spelers',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::KpiPlayers => 'Hoeveel spelers er actief zijn, en hoeveel erbij kwamen.',
            self::KpiRating => 'Het gemiddelde kaartcijfer, en of het stijgt of daalt.',
            self::KpiReports => 'Wat je trainers deze week hebben ingevuld.',
            self::KpiRevenue => 'Wat er deze maand binnenkwam.',
            self::Development => 'Wie stijgt, wie blijft achter, en hoeveel spelers een actueel rapport hebben.',
            self::Finance => 'Openstaand, lopende abonnementen en de verwachte jaaromzet.',
            self::Trainings => 'De eerstvolgende drie trainingen.',
            self::Birthdays => 'Wie er de komende weken jarig is.',
            self::MyTrainings => 'De trainingen die jij geeft, met afvinken en rapporten binnen handbereik.',
            self::MyPlayers => 'De spelers uit jouw groepen, en wanneer ze voor het laatst beoordeeld zijn.',
        };
    }

    /**
     * Hoeveel kolommen van de twaalf deze widget inneemt op een groot scherm.
     *
     * Het eerste formaat is de standaard. Een lege lijst zou betekenen dat de
     * widget geen plek heeft; dat kan niet.
     *
     * @return list<int>
     */
    public function sizes(): array
    {
        return match ($this) {
            // Een kerncijfer is een kwart of een half; breder wordt een leeg vlak.
            self::KpiPlayers, self::KpiRating, self::KpiReports, self::KpiRevenue => [3, 6],
            // Het onderscheidende deel van het product: standaard tweederde.
            self::Development => [8, 12],
            self::Finance => [4, 6, 12],
            self::Trainings, self::Birthdays => [6, 4, 12],
            // Van de trainer, en dus zijn hoofdmoot: standaard breed.
            self::MyTrainings, self::MyPlayers => [6, 12, 4],
        };
    }

    public function defaultSize(): int
    {
        return $this->sizes()[0];
    }

    /** Hoe hoog in het raster, in rijen van 40 pixels. */
    public function height(): int
    {
        return match ($this) {
            self::KpiPlayers, self::KpiRating, self::KpiReports, self::KpiRevenue => 3,
            self::Development => 7,
            self::Finance => 7,
            self::Trainings, self::Birthdays => 5,
            self::MyTrainings, self::MyPlayers => 8,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::KpiPlayers => 'players',
            self::KpiRating => 'rating',
            self::KpiReports => 'reports',
            self::KpiRevenue => 'revenue',
            self::Development => 'development',
            self::Finance => 'revenue',
            self::Trainings => 'trainings',
            self::Birthdays => 'birthdays',
            self::MyTrainings => 'trainings',
            self::MyPlayers => 'players',
        };
    }

    /** Welke functie deze school aan moet hebben staan, of null. */
    public function feature(): ?Feature
    {
        return match ($this) {
            self::KpiRating, self::KpiReports, self::Development, self::MyPlayers => Feature::Ontwikkeling,
            self::KpiRevenue, self::Finance => Feature::Betalingen,
            default => null,
        };
    }

    /** Geld is van de eigenaar; een trainer krijgt het niet eens aangeboden. */
    public function ownerOnly(): bool
    {
        return in_array($this, [self::KpiRevenue, self::Finance], true);
    }

    /**
     * Alleen voor wie zelf voor de groep staat.
     *
     * Een eigenaar die niet traint heeft niets aan "mijn spelers"; hij kan de
     * widget wel plaatsen, want bij een kleine school geeft hij zelf training.
     * Een ouder of speler komt hier sowieso niet: die heeft een eigen dashboard.
     */
    public function trainerOnly(): bool
    {
        return in_array($this, [self::MyTrainings, self::MyPlayers], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
