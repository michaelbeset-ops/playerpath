<?php

namespace App\Support\Rating;

use Illuminate\Support\Carbon;

/**
 * De leeftijdscategorie van een speler, uit zijn geboortejaar.
 *
 * Uit de geboortedatum en niet uit de groep: een speler kan in twee groepen
 * zitten (keepers én veld), en "leeftijd" staat bewust niet op de speler
 * (zie CLAUDE.md). De categorie is dus altijd afgeleid en kan niet uit de pas
 * lopen.
 *
 * ## De regel
 *
 * Jaargangen op geboortejaar, zoals de KNVB dat doet, met even banden: O8,
 * O10, O12, O14, O16, O18. "O12" betekent "onder twaalf": wie in het
 * seizoensjaar elf wordt hoort erbij. De leeftijd wordt genomen op 1 januari
 * van het jaar waarin het seizoen begint, en het seizoen begint in augustus.
 *
 * Wie zeventien of ouder is valt in O18+. Daarboven verschilt de lat niet meer
 * per jaar.
 */
final class AgeCategory
{
    /** @var list<int> */
    public const BANDEN = [8, 10, 12, 14, 16, 18];

    public static function forBirthDate(Carbon $geboren, ?Carbon $op = null, int $seasonStartMonth = 8): string
    {
        $seizoensjaar = self::seasonStartYear($op ?? now(), $seasonStartMonth);
        $leeftijd = $seizoensjaar - $geboren->year;

        foreach (self::BANDEN as $band) {
            if ($leeftijd < $band) {
                return 'O'.$band;
            }
        }

        return 'O18+';
    }

    /**
     * Het jaar waarin het lopende seizoen begon.
     *
     * In september 2026 is dat 2026; in maart 2027 nog steeds 2026, want het
     * seizoen loopt door tot de zomer.
     */
    public static function seasonStartYear(Carbon $op, int $seasonStartMonth = 8): int
    {
        return $op->month >= $seasonStartMonth ? $op->year : $op->year - 1;
    }

    /** "2026/27" - het seizoen zoals een school het noemt. */
    public static function seasonLabel(Carbon $op, int $seasonStartMonth = 8): string
    {
        $start = self::seasonStartYear($op, $seasonStartMonth);

        return $start.'/'.substr((string) ($start + 1), 2);
    }

    /** Een leesbare omschrijving voor op de kaart en in de uitleg. */
    public static function describe(string $category): string
    {
        return $category === 'O18+' ? 'Senioren' : 'Onder '.substr($category, 1);
    }
}
