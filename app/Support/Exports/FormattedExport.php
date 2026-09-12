<?php

namespace App\Support\Exports;

/**
 * Een overzicht dat zegt wat voor soort waarde er in elke kolom staat.
 *
 * Daarmee kan de writer Excel iets nuttigs geven: een datum als echte datum
 * (sorteerbaar), een bedrag met euroteken en twee decimalen, en onderaan een
 * totaalregel. Zonder dit is alles tekst, en dan rekent Excel nergens mee.
 *
 * Soorten: `text`, `date` (d-m-Y), `money` (euro's als getal), `number`, `int`.
 */
interface FormattedExport
{
    /** @return list<string> één soort per kolom, in de volgorde van headings() */
    public function types(): array;

    /** @return list<int> kolomindexen (vanaf 0) die onderaan opgeteld worden */
    public function totals(): array;
}
