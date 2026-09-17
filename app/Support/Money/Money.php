<?php

namespace App\Support\Money;

/**
 * Bedragen zijn overal integers in centen - nooit floats. Zie CLAUDE.md 3.2.
 *
 * Deze klasse doet één ding: centen omzetten naar iets wat een mens leest, en
 * omgekeerd. Rekenen gebeurt altijd in centen; alleen de weergave is euro's.
 */
class Money
{
    /** 1250 => "€ 12,50" */
    public static function format(?int $cents): string
    {
        if ($cents === null) {
            return '-';
        }

        $negatief = $cents < 0;
        $cents = abs($cents);

        $bedrag = number_format($cents / 100, 2, ',', '.');

        return ($negatief ? '- ' : '').'€ '.$bedrag;
    }

    /**
     * "12,50" of "12.50" of "€ 12,50" => 1250
     *
     * Bewust via string en round(): 12.50 * 100 geeft in floating point
     * 1249.9999999999998, en dat is precies waarom geld geen float mag zijn.
     */
    /**
     * Is dit een bedrag zoals mensen het typen? "12", "12,50", "1.250,00",
     * "12.50" of "€ 12,50". Geen letters, hooguit twee decimalen.
     */
    public static function isValid(?string $bedrag): bool
    {
        $schoon = trim(str_replace(['€', ' '], '', (string) $bedrag));

        return (bool) preg_match('/^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?$|^\d+(\.\d{1,2})?$/', $schoon);
    }

    public static function toCents(string $bedrag): int
    {
        $schoon = preg_replace('/[^0-9,.\-]/', '', $bedrag) ?? '';

        // Nederlandse invoer: punt is duizendtal, komma is decimaal.
        if (str_contains($schoon, ',')) {
            $schoon = str_replace('.', '', $schoon);
            $schoon = str_replace(',', '.', $schoon);
        }

        return (int) round(((float) $schoon) * 100);
    }
}
