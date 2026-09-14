<?php

namespace App\Support\Rating;

use App\Models\School;

/**
 * De knoppen van het ratingsysteem, per school.
 *
 * Alles wat een getal is staat hier en nergens anders: hoeveel XP een training
 * oplevert, waar de levelgrenzen liggen, hoeveel rapporten er meewegen. Zo kan
 * een school die haar spelers vaker wil belonen dat instellen zonder dat er
 * ergens een constante verandert die ook alle andere scholen raakt.
 *
 * Zelfde afspraak als bij de functies per school: **de standaarden staan in
 * code**, in `schools.rating_settings` staan alleen afwijkingen. Een nieuwe
 * knop krijgt dan bij elke school vanzelf zijn standaard.
 *
 * ## De standaarden, en waarom
 *
 * - **Aanwezig: 10 XP.** Wie een blok van twaalf weken elke week komt en
 *   telkens een rapport krijgt, zit rond de 250 en heeft dan zilver; wie
 *   ook groeit, komt in een seizoen bij goud. Trouw komen brengt je dus
 *   omhoog, ook zonder talent - dat is het stimuleringsdeel.
 * - **Rapport: 5 XP**, plus **2 XP per punt groei** ten opzichte van het vorige
 *   rapport, tot 30. Groei telt, maar niet zó zwaar dat één goede dag een level
 *   is.
 * - **Levels op 0 / 251 / 501 / 751.** Brons tot 250, zilver tot 500, goud tot
 *   750, daarboven Special. De punten zijn seizoensgebonden (zie
 *   SchoolSeason), dus elk blok begint iedereen weer bij brons en is goud
 *   binnen één seizoen haalbaar voor wie trouw komt en groeit.
 * - **Minimale rating per level staat uit.** Een school die vindt dat goud ook
 *   een zekere rating vereist kan dat aanzetten; standaard leggen we dat niet op,
 *   want het level is er om inzet te belonen en de rating staat er al naast.
 */
class RatingSettings
{
    /** Variant A: de kaart met een rating per categorie en overall. */
    public const PRESTATIE = 'prestatie';

    /** Variant B: de kaart als beloning voor inzet, zonder cijfers. */
    public const INZET = 'inzet';

    /** De kleuren waaruit een school haar voortgangsniveaus kiest. */
    public const KLEURENPALET = ['rood', 'oranje', 'geel', 'groen', 'blauw', 'paars'];

    /** @var array<string, mixed> */
    public const STANDAARD = [
        'xp_attendance' => 10,
        'xp_report' => 5,
        'xp_per_point_growth' => 2,
        'xp_growth_cap' => 30,
        'reports_in_average' => 3,
        'levels' => [
            ['key' => 'brons', 'label' => 'Brons', 'xp' => 0, 'min_rating' => null],
            ['key' => 'zilver', 'label' => 'Zilver', 'xp' => 251, 'min_rating' => null],
            ['key' => 'goud', 'label' => 'Goud', 'xp' => 501, 'min_rating' => null],
            // De sleutel blijft 'elite' (die staat in opgeslagen seizoenskaarten
            // en in de kleuren van het frame); het label is "Special".
            ['key' => 'elite', 'label' => 'Special', 'xp' => 751, 'min_rating' => null],
        ],
        // Het seizoen loopt van augustus tot juli, zoals in het Nederlandse voetbal.
        'season_start_month' => 8,
        // Bij de prestatiekaart: tonen de rapporten cijfers of vier kleuren. Zie Grade.
        'grading' => 'cijfers',
        // Welke kaart de school gebruikt. De prestatiekaart is de standaard voor
        // wie niets koos (zo bleef het voor bestaande scholen zoals het was); de
        // wizard zet de inzetkaart voor, als aanbevolen keuze.
        'card_mode' => 'prestatie',
        // Inzetkaart: de treden die een trainer na de training aantikt, met hun
        // punten. Geen negatieve trede; niets kiezen is nul extra. Drie of vier.
        // Een training vol inzet levert 10 + 15 + 15 = 40 op; twaalf weken zo
        // is goud, twaalf weken gewoon goed meedoen (10 + 10 + 5) is zilver.
        'effort_levels' => [
            ['key' => 'n1', 'label' => 'Goed bezig', 'points' => 5],
            ['key' => 'n2', 'label' => 'Hard gewerkt', 'points' => 10],
            ['key' => 'n3', 'label' => 'Uitblinker', 'points' => 15],
        ],
        'attitude_levels' => [
            ['key' => 'n1', 'label' => 'Luistert goed', 'points' => 5],
            ['key' => 'n2', 'label' => 'Top houding', 'points' => 10],
            ['key' => 'n3', 'label' => 'Voorbeeld voor de groep', 'points' => 15],
        ],
        // Inzetkaart: de kleurenschaal voor het begin- en eindniveau per cursus.
        'progress_levels' => [
            ['key' => 'n1', 'label' => 'Werkpunt', 'color' => 'rood'],
            ['key' => 'n2', 'label' => 'Op weg', 'color' => 'oranje'],
            ['key' => 'n3', 'label' => 'Goed', 'color' => 'groen'],
            ['key' => 'n4', 'label' => 'Sterk', 'color' => 'blauw'],
        ],
    ];

    /** @var array<string, mixed> */
    protected array $waarden;

    public function __construct(?School $school = null)
    {
        $this->waarden = array_replace(self::STANDAARD, array_filter($school?->rating_settings ?? [], fn ($v) => $v !== null));
    }

    public static function for(?School $school): self
    {
        return new self($school);
    }

    public function xpForAttendance(): int
    {
        return (int) $this->waarden['xp_attendance'];
    }

    public function xpForReport(): int
    {
        return (int) $this->waarden['xp_report'];
    }

    public function xpPerPointGrowth(): int
    {
        return (int) $this->waarden['xp_per_point_growth'];
    }

    public function xpGrowthCap(): int
    {
        return (int) $this->waarden['xp_growth_cap'];
    }

    public function reportsInAverage(): int
    {
        return max(1, (int) $this->waarden['reports_in_average']);
    }

    /** 'kleuren' of 'cijfers'; zie Grade. */
    public function grading(): string
    {
        return $this->waarden['grading'] === Grade::CIJFERS ? Grade::CIJFERS : Grade::KLEUREN;
    }

    /** Welke kaart: 'prestatie' (ratings) of 'inzet' (punten voor inzet). */
    public function cardMode(): string
    {
        return $this->waarden['card_mode'] === self::INZET ? self::INZET : self::PRESTATIE;
    }

    public function usesEffort(): bool
    {
        return $this->cardMode() === self::INZET;
    }

    /** @return list<array{key: string, label: string, points: int}> */
    public function effortLevels(): array
    {
        return self::treden($this->waarden['effort_levels'], self::STANDAARD['effort_levels']);
    }

    /** @return list<array{key: string, label: string, points: int}> */
    public function attitudeLevels(): array
    {
        return self::treden($this->waarden['attitude_levels'], self::STANDAARD['attitude_levels']);
    }

    /** @return array{key: string, label: string, points: int}|null */
    public function effortLevel(?string $key): ?array
    {
        return $key === null ? null : collect($this->effortLevels())->firstWhere('key', $key);
    }

    /** @return array{key: string, label: string, points: int}|null */
    public function attitudeLevel(?string $key): ?array
    {
        return $key === null ? null : collect($this->attitudeLevels())->firstWhere('key', $key);
    }

    /** @return list<array{key: string, label: string, color: string}> */
    public function progressLevels(): array
    {
        $uit = [];

        foreach (is_array($this->waarden['progress_levels']) ? array_values($this->waarden['progress_levels']) : [] as $niveau) {
            $label = trim((string) ($niveau['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $uit[] = [
                'key' => 'n'.(count($uit) + 1),
                'label' => $label,
                'color' => in_array($niveau['color'] ?? null, self::KLEURENPALET, true) ? $niveau['color'] : 'groen',
            ];
        }

        return count($uit) >= 2 ? $uit : self::STANDAARD['progress_levels'];
    }

    /**
     * De XP-bronnen die bij deze kaart niet meetellen.
     *
     * Alle boekingen blijven bestaan; de som op de speler telt alleen wat bij
     * de kaart hoort. Zo begint bij de inzetkaart iedereen gelijk, ook als er
     * eerder rapporten waren, en is terugwisselen de stand van vroeger.
     *
     * @return list<string>
     */
    public function excludedXpSources(): array
    {
        return $this->usesEffort() ? ['report', 'growth'] : ['inzet'];
    }

    /**
     * Treden opschonen: een naam is verplicht, de sleutels volgen de volgorde.
     *
     * @param  list<array{key: string, label: string, points: int}>  $standaard
     * @return list<array{key: string, label: string, points: int}>
     */
    protected static function treden(mixed $lijst, array $standaard): array
    {
        if (! is_array($lijst)) {
            return $standaard;
        }

        $uit = [];

        foreach (array_values($lijst) as $trede) {
            $label = trim((string) ($trede['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $uit[] = ['key' => 'n'.(count($uit) + 1), 'label' => $label, 'points' => max(0, (int) ($trede['points'] ?? 0))];
        }

        return count($uit) >= 2 ? $uit : $standaard;
    }

    public function seasonStartMonth(): int
    {
        return (int) $this->waarden['season_start_month'];
    }

    /**
     * De levels, laag naar hoog.
     *
     * @return list<array{key: string, label: string, xp: int, min_rating: int|null}>
     */
    public function levels(): array
    {
        $levels = array_values($this->waarden['levels']);
        usort($levels, fn ($a, $b) => $a['xp'] <=> $b['xp']);

        return $levels;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->waarden;
    }
}
