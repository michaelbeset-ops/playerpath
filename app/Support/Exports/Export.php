<?php

namespace App\Support\Exports;

/**
 * Eén exporteerbaar overzicht.
 *
 * Elk overzicht is een klasse die deze interface implementeert en zich
 * aanmeldt in ExportRegistry. Meer is er niet voor nodig: het scherm, de
 * knoppen en het schrijven naar CSV of Excel zijn generiek.
 *
 * Zo komt het betalingsoverzicht er later bij als één klasse, zodra Mollie
 * is aangesloten - zonder aan het scherm of de writer te hoeven zitten.
 *
 * Regels voor rows():
 * - alleen data van de actieve school (de global scope regelt dat, maar
 *   gebruik geen withoutSchoolScope);
 * - geld altijd in centen ophalen en pas hier omzetten naar een bedrag in
 *   euro's als getal (12.5), nooit als tekst met een euroteken - anders kan
 *   Excel er niet mee rekenen;
 * - datums als d-m-Y, tijden als H:i.
 */
interface Export
{
    /** Korte sleutel voor de URL, bijvoorbeeld "players". */
    public function key(): string;

    public function title(): string;

    public function description(): string;

    /** Heeft dit overzicht een van/tot-periode? */
    public function supportsDateRange(): bool;

    /** @return list<string> */
    public function headings(): array;

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return iterable<int, list<scalar|null>>
     */
    public function rows(array $filters): iterable;
}
