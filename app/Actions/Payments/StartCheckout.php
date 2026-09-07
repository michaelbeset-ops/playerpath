<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Payments\PaymentGateway;

/**
 * Een betaling bij de provider starten en teruggeven waar de betaler heen moet.
 *
 * Eén plek, want dit gebeurt inmiddels op drie: de ouder die in zijn eigen
 * overzicht op "Nu betalen" drukt, de link uit een e-mail, en de shop. Zouden
 * die elk hun eigen versie hebben, dan gaat er vroeg of laat één de webhook-URL
 * vergeten of een tweede betaling aanmaken voor dezelfde rekening.
 *
 * Een lopende betaling wordt hervat in plaats van dat er een tweede naast komt:
 * anders staat er straks twee keer hetzelfde bedrag open omdat iemand halverwege
 * iDEAL zijn browser sloot.
 */
class StartCheckout
{
    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * @return string|null de betaalpagina van de provider, of null als er geen
     *                     provider is aangesloten
     */
    public function handle(Payment $payment, string $returnUrl, ?string $customerReference = null): ?string
    {
        if (! $this->gateway->isConnected()) {
            return null;
        }

        if ($payment->checkout_url !== null && $payment->status === PaymentStatus::Open) {
            return $payment->checkout_url;
        }

        $remote = $this->gateway->start($payment, $returnUrl, route('webhooks.mollie'), $customerReference);

        $payment->forceFill([
            'external_reference' => $remote->reference,
            'checkout_url' => $remote->checkoutUrl,
        ])->save();

        return $remote->checkoutUrl;
    }
}
