<?php

namespace App\Support\Payments;

use RuntimeException;

/**
 * Er wordt een betaling gevraagd terwijl er geen provider is aangesloten.
 *
 * Dit hoort een programmeerfout te zijn, geen gebruikersfout: elk scherm
 * vraagt eerst isConnected() en toont anders geen betaalknop. Vandaar een
 * exception en geen nette melding — stilletjes niets doen zou erger zijn.
 */
class GatewayNotConnected extends RuntimeException
{
    public static function make(): self
    {
        return new self('Er is geen betaalprovider aangesloten.');
    }
}
