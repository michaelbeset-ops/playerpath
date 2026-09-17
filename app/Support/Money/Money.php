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
     * Is dit een bedrag zoals mensen het typen? "12", "12,50", "1.250,00",
     * "12.50" of "€ 12,50". Geen letters, hooguit twee decimalen.
     */
    public static function isValid(?string $bedrag): bool
    {
        $schoon = trim(str_replace(['€', ' '], '', (string) $bedrag));

        return (bool) preg_match('/^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?$|^\d+(\.\d{1,2})?$/', $schoon);
    }

    /**
     * "12,50" of "12.50" of "€ 12,50" => 1250; "1.250" en "1.250,00" => 125000.
     *
     * Bewust als tekst gesplitst en met gehele getallen gerekend: 12.50 * 100
     * geeft in floating point 1249.9999999999998, en dat is precies waarom
     * geld geen float mag zijn.
     *
     * Een punt is een duizendtal als er een komma staat, of als hij in
     * groepjes van drie staat zonder komma ("1.250"). Anders is hij het
     * decimaalteken ("12.50"). Zo betekent een bedrag hier hetzelfde als in
     * isValid(). Een derde decimaal rondt af, half naar boven.
     */
    public static function toCents(string $bedrag): int
    {
        $schoon = preg_replace('/[^0-9,.\-]/', '', $bedrag) ?? '';
        $negatief = str_starts_with($schoon, '-');
        $schoon = str_replace('-', '', $schoon);

        if (str_contains($schoon, ',')) {
            // Nederlandse invoer: punt is duizendtal, komma is decimaal.
            [$heel, $decimalen] = explode(',', str_replace('.', '', $schoon), 2);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $schoon)) {
            [$heel, $decimalen] = [str_replace('.', '', $schoon), ''];
        } else {
            [$heel, $decimalen] = array_pad(explode('.', $schoon, 2), 2, '');
        }

        $heel = preg_replace('/\D/', '', $heel) ?? '';
        $decimalen = substr(str_pad(preg_replace('/\D/', '', $decimalen) ?? '', 3, '0'), 0, 3);
        $duizendsten = (int) ($heel === '' ? '0' : $heel) * 1000 + (int) $decimalen;
        $centen = intdiv($duizendsten + 5, 10);

        return $negatief ? -$centen : $centen;
    }
}
