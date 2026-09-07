<?php

namespace App\Enums;

/**
 * Een incassomachtiging bij de betaalprovider. Wij bewaren alleen het kenmerk;
 * of hij nog geldig is vragen we bij elke incasso opnieuw aan de provider.
 */
enum MandateStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In aanvraag',
            self::Valid => 'Geldig',
            self::Revoked => 'Ingetrokken',
        };
    }
}
