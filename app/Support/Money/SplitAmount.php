<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Een bedrag in centen eerlijk in delen knippen.
 *
 * € 100,00 in 3 termijnen is niet 3 × € 33,33 - dan mist er een cent en klopt
 * de boekhouding niet. De restcenten gaan naar de eerste termijnen, zodat de
 * som altijd exact het oorspronkelijke bedrag is en de laatste termijn nooit
 * hoger uitvalt dan de eerste.
 */
final class SplitAmount
{
    /** @return list<int> */
    public static function into(int $cents, int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Een bedrag splitsen kan alleen in één of meer delen.');
        }

        $basis = intdiv($cents, $parts);
        $rest = $cents - ($basis * $parts);

        return array_map(
            fn (int $i) => $basis + ($i < $rest ? 1 : 0),
            range(0, $parts - 1),
        );
    }

    /**
     * Een bedrag naar rato over gewichten verdelen, met dezelfde belofte: de
     * som is exact het bedrag. Eerst naar beneden afgerond (intdiv), daarna
     * gaan de restcenten één voor één naar de zwaarste gewichten.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, int>  $weights  niet-negatief
     * @return array<TKey, int>
     */
    public static function proportional(int $cents, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $totaal = array_sum(array_map(fn (int $w) => max(0, $w), $weights));

        if ($totaal <= 0) {
            // Niets om naar te verdelen: alles op de eerste.
            $uit = array_map(fn () => 0, $weights);
            $uit[array_key_first($weights)] = $cents;

            return $uit;
        }

        $negatief = $cents < 0;
        $rest = abs($cents);
        $uit = [];

        foreach ($weights as $sleutel => $gewicht) {
            $uit[$sleutel] = intdiv(abs($cents) * max(0, $gewicht), $totaal);
            $rest -= $uit[$sleutel];
        }

        $volgorde = $weights;
        arsort($volgorde);

        foreach (array_keys($volgorde) as $sleutel) {
            if ($rest <= 0) {
                break;
            }

            $uit[$sleutel]++;
            $rest--;
        }

        return $negatief ? array_map(fn (int $c) => -$c, $uit) : $uit;
    }
}
