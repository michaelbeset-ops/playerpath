<?php

namespace App\Enums;

/** Wat een regel op een order is. Korting is een regel met een negatief bedrag. */
enum OrderLineType: string
{
    case Offering = 'offering';
    case RegistrationFee = 'registration_fee';
    case Kit = 'kit';
    case Trial = 'trial';
    case Discount = 'discount';

    public function label(): string
    {
        return match ($this) {
            self::Offering => 'Aanbod',
            self::RegistrationFee => 'Inschrijfgeld',
            self::Kit => 'Kledingpakket',
            self::Trial => 'Proefles',
            self::Discount => 'Korting',
        };
    }
}
