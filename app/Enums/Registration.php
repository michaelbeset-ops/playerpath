<?php

namespace App\Enums;

/** Wat de speler of ouder vooraf doorgeeft. */
enum Registration: string
{
    case Attending = 'attending';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Attending => 'Aangemeld',
            self::Declined => 'Afgemeld',
        };
    }
}
