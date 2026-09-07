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
 * - **Aanwezig: 10 XP.** Wie een seizoen lang elke week komt zit rond de 400,
 *   en dat is precies goud. Trouw komen brengt je dus naar goud, ook zonder
 *   talent — dat is het stimuleringsdeel.
 * - **Rapport: 5 XP**, plus **2 XP per punt groei** ten opzichte van het vorige
 *   rapport, tot 30. Groei telt, maar niet zó zwaar dat één goede dag een level
 *   is.
 * - **Levels op 0 / 150 / 400 / 900.** Zilver na een paar maanden, goud na een
 *   seizoen, elite na twee. Elk level moet haalbaar voelen én iets betekenen.
 * - **Minimale rating per level staat uit.** Een school die vindt dat goud ook
 *   een zekere rating vereist kan dat aanzetten; standaard leggen we dat niet op,
 *   want het level is er om inzet te belonen en de rating staat er al naast.
 */
class RatingSettings
{
    /** @var array<string, mixed> */
    public const STANDAARD = [
        'xp_attendance' => 10,
        'xp_report' => 5,
        'xp_per_point_growth' => 2,
        'xp_growth_cap' => 30,
        'reports_in_average' => 3,
        'levels' => [
            ['key' => 'brons', 'label' => 'Brons', 'xp' => 0, 'min_rating' => null],
            ['key' => 'zilver', 'label' => 'Zilver', 'xp' => 150, 'min_rating' => null],
            ['key' => 'goud', 'label' => 'Goud', 'xp' => 400, 'min_rating' => null],
            ['key' => 'elite', 'label' => 'Elite', 'xp' => 900, 'min_rating' => null],
        ],
        // Het seizoen loopt van augustus tot juli, zoals in het Nederlandse voetbal.
        'season_start_month' => 8,
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
