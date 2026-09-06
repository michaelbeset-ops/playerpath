<?php

namespace App\Enums;

/**
 * De kerncijfers die op het dashboard kunnen staan.
 *
 * Dit is de enige lijst. Het instellingenscherm, de opslag en het dashboard
 * zelf lezen allemaal deze enum, dus een cijfer erbij is één case.
 *
 * **Hernoem een waarde nooit** zonder migratie: hij staat opgeslagen in
 * `users.dashboard_preferences`, en iemand die een tegel bewust had uitgezet
 * zou hem stilzwijgend terugkrijgen.
 *
 * Niet alles staat standaard aan. Veertien getallen naast elkaar is geen
 * dashboard maar een muur; wie meer wil zetten ze zelf aan.
 */
enum DashboardTile: string
{
    case Players = 'players';
    case Keepers = 'keepers';
    case Groups = 'groups';
    case Rating = 'rating';
    case Reports = 'reports';
    case Goals = 'goals';
    case Trainings = 'trainings';
    case Attendance = 'attendance';
    case Birthdays = 'birthdays';
    case Enrollments = 'enrollments';
    case Revenue = 'revenue';
    case Outstanding = 'outstanding';
    case Overdue = 'overdue';
    case Subscriptions = 'subscriptions';

    public function label(): string
    {
        return match ($this) {
            self::Players => 'Actieve spelers',
            self::Keepers => 'Keepers',
            self::Groups => 'Groepen',
            self::Rating => 'Gemiddelde rating',
            self::Reports => 'Rapporten deze week',
            self::Goals => 'Spelers met een doel',
            self::Trainings => 'Trainingen deze week',
            self::Attendance => 'Opkomst',
            self::Birthdays => 'Jarig deze maand',
            self::Enrollments => 'Open inschrijvingen',
            self::Revenue => 'Omzet deze maand',
            self::Outstanding => 'Openstaand',
            self::Overdue => 'Achterstallig',
            self::Subscriptions => 'Lopende abonnementen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Players => 'Hoeveel spelers er nu actief zijn.',
            self::Keepers => 'Het aantal keepers, los van de veldspelers.',
            self::Groups => 'Het aantal actieve groepen.',
            self::Rating => 'Het gemiddelde kaartcijfer over alle spelers met een rapport.',
            self::Reports => 'Wat je trainers deze week hebben ingevuld.',
            self::Goals => 'Voor hoeveel spelers er een doel loopt.',
            self::Trainings => 'Wat er deze week op het rooster staat.',
            self::Attendance => 'Het percentage aanwezig over de laatste 30 dagen.',
            self::Birthdays => 'Hoeveel spelers er deze maand jarig zijn.',
            self::Enrollments => 'Inschrijvingen die nog op je beslissing wachten.',
            self::Revenue => 'Wat er deze maand binnenkwam.',
            self::Outstanding => 'Wat er nog openstaat.',
            self::Overdue => 'Wat er te laat is en om actie vraagt.',
            self::Subscriptions => 'Het aantal lopende abonnementen.',
        };
    }

    /** Welke functie deze school aan moet hebben staan, of null. */
    public function feature(): ?Feature
    {
        return match ($this) {
            self::Rating, self::Reports, self::Goals => Feature::Ontwikkeling,
            self::Trainings, self::Attendance => null,
            self::Enrollments => Feature::Inschrijvingen,
            self::Revenue, self::Outstanding, self::Overdue, self::Subscriptions => Feature::Betalingen,
            default => null,
        };
    }

    /** Geld is van de eigenaar; een trainer krijgt het niet eens aangeboden. */
    public function ownerOnly(): bool
    {
        return in_array($this, [self::Revenue, self::Outstanding, self::Overdue, self::Subscriptions], true);
    }

    /**
     * Staat deze tegel aan bij iemand die nooit iets heeft ingesteld?
     *
     * Vier stuks: dat is één rij op een groot scherm en twee op een telefoon.
     */
    public function defaultVisible(): bool
    {
        return in_array($this, [self::Players, self::Rating, self::Reports, self::Attendance], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
