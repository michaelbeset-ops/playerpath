<?php

namespace App\Support\Exports;

/**
 * Een overzicht met meerdere tabbladen in Excel.
 *
 * headings()/rows() van Export blijven het hoofdtabblad: dat is wat je krijgt
 * als je CSV kiest, want CSV kent geen tabbladen. Excel krijgt alle sheets.
 */
interface WorkbookExport extends Export
{
    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return list<Sheet>
     */
    public function sheets(array $filters): array;
}
