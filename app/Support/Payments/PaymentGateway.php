<?php

namespace App\Support\Payments;

use App\Models\Payment;

/**
 * De naad waar de betaalprovider inklikt.
 *
 * De rest van de app praat alleen met deze interface, nooit rechtstreeks met
 * Mollie. Daardoor is een andere provider later één nieuwe klasse, en hoeft er
 * aan de schermen niets te veranderen.
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

    /**
     * Start een betaling en geef terug waar de betaler naartoe moet.
     *
     * De provider krijgt zowel een redirectUrl (waar de mens landt) als een
     * webhookUrl (waar de waarheid binnenkomt). Die twee zijn niet inwisselbaar:
     * een mens die de browser sluit meldt niets terug, de webhook wel.
     *
     * @throws GatewayNotConnected
     */
    public function start(Payment $payment, string $returnUrl, string $webhookUrl): RemotePayment;

    /**
     * Vraag de provider hoe het met deze betaling staat.
     *
     * Dit is de enige bron van waarheid over betaald of niet. Wat de browser
     * terugmeldt is niet meer dan een hint: die kan verouderd of vervalst zijn.
     *
     * @throws GatewayNotConnected
     */
    public function fetch(string $reference): RemotePayment;
}
