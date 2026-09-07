<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Ochtend, middag of avond.
 *
 * Beschikbaarheid vragen in kwartieren is een agenda bouwen die niemand invult.
 * Een trainer weet wél of hij doordeweeks 's avonds kan; dat is precies genoeg
 * om een planning op te maken en het is op een telefoon in drie tikken gedaan.
 *
 * De grenzen staan hier op één plek, zodat het scherm en de waarschuwing op het
 * eigenaar-dashboard hetzelfde bedoelen met "avond".
 */
enum Daypart: string
{
    case Ochtend = 'ochtend';
    case Middag = 'middag';
    case Avond = 'avond';

    public function label(): string
    {
        return match ($this) {
            self::Ochtend => 'Ochtend',
            self::Middag => 'Middag',
            self::Avond => 'Avond',
        };
    }

    /** De tijden erbij, zodat een trainer weet waar hij ja tegen zegt. */
    public function hint(): string
    {
        return match ($this) {
            self::Ochtend => 'tot 12:00',
            self::Middag => '12:00 - 17:00',
            self::Avond => 'vanaf 17:00',
        };
    }

    /** In welk dagdeel valt dit moment? Gaat op de begintijd. */
    public static function forTime(CarbonInterface $moment): self
    {
        return match (true) {
            $moment->hour < 12 => self::Ochtend,
            $moment->hour < 17 => self::Middag,
            default => self::Avond,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
