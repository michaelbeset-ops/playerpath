<?php

namespace App\Enums;

/**
 * De stand van een order: de financiële kop boven één of meer inschrijvingen.
 * De volledige statusmachine komt in onderdeel 4; dit zijn de waarden die de
 * tabel nu kent.
 */
enum OrderStatus: string
{
    case Concept = 'concept';
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Open => 'Wacht op betaling',
            self::Paid => 'Betaald',
            self::Cancelled => 'Geannuleerd',
            self::Refunded => 'Terugbetaald',
        };
    }
}
