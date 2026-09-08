<?php

namespace App\Enums;

/**
 * De status van een losse aanmelding op een training.
 *
 * Aangevraagd is er alleen als de training goedkeuring vraagt; anders is een
 * aanmelding meteen bevestigd, of hij komt op de wachtlijst als het vol is.
 */
enum TrainingEnrollmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Wacht op goedkeuring',
            self::Confirmed => 'Ingeschreven',
            self::Waitlisted => 'Op de wachtlijst',
            self::Declined => 'Afgewezen',
            self::Cancelled => 'Afgemeld',
        };
    }

    /** Neemt deze aanmelding een plek in? */
    public function takesSpot(): bool
    {
        return $this === self::Confirmed;
    }

    /** Leeft deze aanmelding nog: staat het kind ergens in de rij? */
    public function isActive(): bool
    {
        return in_array($this, [self::Requested, self::Confirmed, self::Waitlisted], strict: true);
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isActive()));
    }
}
