<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Models\Player;
use App\Models\User;

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
     * Wordt er een klantkenmerk meegegeven, dan is dit een eerste betaling die
     * meteen een incassomandaat vastlegt: daarna kan er automatisch worden
     * afgeschreven zonder dat de ouder er nog iets voor hoeft te doen.
     *
     * @throws GatewayNotConnected
     */
    public function start(Payment $payment, string $returnUrl, string $webhookUrl, ?string $customerReference = null): RemotePayment;

    /**
     * Vraag de provider hoe het met deze betaling staat.
     *
     * Dit is de enige bron van waarheid over betaald of niet. Wat de browser
     * terugmeldt is niet meer dan een hint: die kan verouderd of vervalst zijn.
     *
     * @throws GatewayNotConnected
     */
    public function fetch(string $reference): RemotePayment;

    /**
     * Het klantkenmerk van deze speler bij de provider, zo nodig aangemaakt.
     *
     * @throws GatewayNotConnected
     */
    public function ensureCustomer(Player $player): string;

    /** Een klant bij de provider voor de ouder die betaalt; het mandaat hangt daaraan. */
    public function ensureCustomerFor(User $user): string;

    /**
     * Mag er van deze klant automatisch afgeschreven worden?
     *
     * Een mandaat kan ingetrokken zijn door de bank of de ouder. Dat vragen we
     * daarom elke incassoronde opnieuw, in plaats van het bij onszelf te
     * onthouden en er straks naast te zitten.
     *
     * @throws GatewayNotConnected
     */
    public function hasValidMandate(string $customerReference): bool;

    /**
     * Schrijf af op een bestaand mandaat. Hier komt geen browser aan te pas:
     * dit gebeurt in een achtergrondtaak, dus er is ook geen betaalscherm.
     *
     * @throws GatewayNotConnected
     */
    public function charge(Payment $payment, string $customerReference, string $webhookUrl): RemotePayment;
}
