<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Models\Player;

/**
 * De stand van zaken zolang er geen betaalprovider is aangesloten.
 *
 * Bewust een echte implementatie en geen null: zo kan elk scherm gewoon de
 * gateway vragen hoe het ervoor staat, in plaats van overal te raden.
 *
 * Betalen gooit een exception in plaats van te doen alsof. Een nepbetaling is
 * het ergste wat een boekhouding kan overkomen.
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

    public function start(Payment $payment, string $returnUrl, string $webhookUrl, ?string $customerReference = null): RemotePayment
    {
        throw GatewayNotConnected::make();
    }

    public function fetch(string $reference): RemotePayment
    {
        throw GatewayNotConnected::make();
    }

    public function ensureCustomer(Player $player): string
    {
        throw GatewayNotConnected::make();
    }

    public function hasValidMandate(string $customerReference): bool
    {
        return false;
    }

    public function charge(Payment $payment, string $customerReference, string $webhookUrl): RemotePayment
    {
        throw GatewayNotConnected::make();
    }
}
