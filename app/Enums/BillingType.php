<?php

namespace App\Enums;

/**
 * Hoe je voor een aanbod betaalt.
 *
 * Bewust los van het soort aanbod: een blok van zes weken kan €120 ineens zijn
 * of €30 per maand, en dat is dezelfde keeperstraining. Zou dit één veld zijn,
 * dan moest een school kiezen tussen "blok" en "abonnement" terwijl ze allebei
 * waar zijn.
 */
enum BillingType: string
{
    case Eenmalig = 'eenmalig';
    case Maandelijks = 'maandelijks';

    public function label(): string
    {
        return match ($this) {
            self::Eenmalig => 'Eenmalig bedrag',
            self::Maandelijks => 'Per maand',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Eenmalig => 'eenmalig',
            self::Maandelijks => 'per maand',
        };
    }

    /** Loopt dit als abonnement, met termijnen en incasso? */
    public function isRecurring(): bool
    {
        return $this === self::Maandelijks;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
