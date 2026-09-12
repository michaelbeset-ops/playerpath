<?php

namespace App\Support\Exports;

/**
 * Eén tabblad in een Excel-werkboek.
 *
 * `types` zegt per kolom wat erin staat (zie FormattedExport), `totals` welke
 * kolommen onderaan een totaal krijgen. Allebei optioneel: zonder is alles
 * tekst en komt er geen totaalregel.
 *
 * @param  list<string>  $headings
 * @param  iterable<int, list<scalar|null>>  $rows
 * @param  list<string>  $types
 * @param  list<int>  $totals
 */
final readonly class Sheet
{
    public function __construct(
        public string $title,
        public array $headings,
        public iterable $rows,
        public array $types = [],
        public array $totals = [],
    ) {}
}
