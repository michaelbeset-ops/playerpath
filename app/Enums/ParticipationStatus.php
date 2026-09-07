<?php

namespace App\Enums;

/** Meedoen, wachten, of afgezegd. */
enum ParticipationStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlist = 'waitlist';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Ingeschreven',
            self::Waitlist => 'Wachtlijst',
            self::Cancelled => 'Geannuleerd',
        };
    }

    /** Telt deze deelname mee voor de bezetting? */
    public function takesSpot(): bool
    {
        return $this === self::Confirmed;
    }
}
