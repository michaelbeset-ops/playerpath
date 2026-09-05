<?php

namespace App\Support\Payments;

/**
 * De naad waar de betaalprovider straks inklikt.
 *
 * De rest van de app praat alleen met deze interface, nooit rechtstreeks met
 * Mollie. Daardoor is aansluiten later één nieuwe klasse plus een sleutel in
 * .env, en hoeft er aan de schermen niets te veranderen.
 *
 * Zolang er geen provider is, is isConnected() false en laat de app dat overal
 * eerlijk zien. Er worden nooit nepbetalingen aangemaakt.
 */
interface PaymentGateway
{
    public function isConnected(): bool;

    /** De naam van de provider, voor in de schermen. */
    public function name(): string;

    /** Wat er nog moet gebeuren voordat er betaald kan worden. */
    public function statusMessage(): string;
}
