<?php

namespace App\Enums;

/** De soorten korting uit de inschrijfinstellingen, plus de kortingscode. */
enum DiscountKind: string
{
    case Family = 'family';
    case Early = 'early';
    case Volume = 'volume';
    case Code = 'code';

    public function label(): string
    {
        return match ($this) {
            self::Family => 'Gezinskorting',
            self::Early => 'Vroegboekkorting',
            self::Volume => 'Volumekorting',
            self::Code => 'Kortingscode',
        };
    }
}
