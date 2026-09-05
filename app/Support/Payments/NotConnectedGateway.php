<?php

namespace App\Support\Payments;

/**
 * De stand van zaken zolang er geen betaalprovider is aangesloten.
 *
 * Bewust een echte implementatie en geen null: zo kan elk scherm gewoon de
 * gateway vragen hoe het ervoor staat, in plaats van overal te raden.
 */
class NotConnectedGateway implements PaymentGateway
{
    public function isConnected(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'Mollie';
    }

    public function statusMessage(): string
    {
        return 'Betalingen zijn nog niet aangesloten. Je kunt abonnementen en bedragen alvast inrichten; '
            .'zodra Mollie gekoppeld is, gaan betalingen automatisch lopen.';
    }
}
