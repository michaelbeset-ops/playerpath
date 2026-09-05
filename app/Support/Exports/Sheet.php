<?php

namespace App\Support\Exports;

/**
 * Eén tabblad in een Excel-werkboek.
 *
 * @param  list<string>  $headings
 * @param  iterable<int, list<scalar|null>>  $rows
 */
final readonly class Sheet
{
    public function __construct(
        public string $title,
        public array $headings,
        public iterable $rows,
    ) {}
}
