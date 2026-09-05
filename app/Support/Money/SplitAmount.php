<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Een bedrag in centen eerlijk in delen knippen.
 *
 * € 100,00 in 3 termijnen is niet 3 × € 33,33 — dan mist er een cent en klopt
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
}
