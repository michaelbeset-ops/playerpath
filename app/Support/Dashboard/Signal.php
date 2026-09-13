<?php

namespace App\Support\Dashboard;

/**
 * Wanneer is een cijfer goed, en wanneer vraagt het aandacht?
 *
 * Eén plek voor die vraag, want anders staat elke component zijn eigen grens te
 * bedenken en betekent groen op het ene vak iets anders dan op het andere. Dan
 * is kleur geen signaal meer maar versiering - precies wat er gebeurde toen
 * bijna alles op het dashboard oranje was.
 *
 * De uitkomst is één woord dat de weergave vertaalt naar een kleur:
 *
 * - `good`    - groen: dit gaat de goede kant op.
 * - `warn`    - oranje: dit kan beter, maar er brandt niets.
 * - `bad`     - rood: hier moet iets gebeuren.
 * - `neutral` - grijs: er valt niets over te zeggen (nog geen cijfers, of
 *               precies gelijk gebleven).
 *
 * "Geen gegevens" is bewust `neutral` en niet oranje. Een school die net begint
 * heeft nergens cijfers, en een dashboard dat haar dan alarmeert over dingen
 * die ze nog niet gedaan kán hebben, leert ze de kleur te negeren.
 */
class Signal
{
    /** Vanaf dit percentage levert de school wat ze belooft. */
    public const GOED_VANAF = 75;

    /** Onder dit percentage valt het product stil. */
    public const SLECHT_ONDER = 50;

    public const GOOD = 'good';

    public const WARN = 'warn';

    public const BAD = 'bad';

    public const NEUTRAL = 'neutral';

    /**
     * Een verandering ten opzichte van de vorige periode.
     *
     * `$higherIsBetter` is er omdat stijgen niet overal goed is: bij
     * openstaande rekeningen is omhoog juist slecht, en dan zou groen het
     * tegendeel zeggen van wat er staat.
     */
    public static function trend(int|float|null $change, bool $higherIsBetter = true): string
    {
        if ($change === null || (int) round($change) === 0) {
            return self::NEUTRAL;
        }

        $omhoog = $change > 0;

        return $omhoog === $higherIsBetter ? self::GOOD : self::BAD;
    }

    /**
     * Een percentage tegen de norm: dekking, opkomst, betaald.
     *
     * Drie treden en niet twee: tussen "goed" en "er valt iets om" zit een
     * gebied waar een school iets aan kan doen zonder dat het misgaat, en dat
     * verschil is precies waarom kleur hier iets zegt.
     */
    public static function ratio(int|float|null $percentage): string
    {
        if ($percentage === null) {
            return self::NEUTRAL;
        }

        if ($percentage >= self::GOED_VANAF) {
            return self::GOOD;
        }

        return $percentage >= self::SLECHT_ONDER ? self::WARN : self::BAD;
    }

    /**
     * Een aantal dat om actie vraagt: openstaande rekeningen, stille spelers.
     *
     * Nul is hier geen "neutraal" maar goed nieuws: er staat niets open.
     */
    public static function count(int $aantal, int $slechtVanaf = 1): string
    {
        if ($aantal === 0) {
            return self::GOOD;
        }

        return $aantal >= $slechtVanaf ? self::WARN : self::NEUTRAL;
    }
}
