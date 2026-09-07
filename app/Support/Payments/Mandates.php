<?php

namespace App\Support\Payments;

use App\Enums\MandateStatus;
use App\Models\Mandate;
use App\Models\Payment;
use App\Models\User;

/**
 * Het incassomandaat per ouder: wie betaalt en tekent.
 *
 * Er staat alleen wat de betaalprovider ons teruggeeft — een klantkenmerk en
 * een mandaatkenmerk. **Nooit een IBAN.** De eerste betaling die een ouder
 * zelf doet (iDEAL, `sequenceType: first`) legt het mandaat vast; de
 * webhook geeft het kenmerk terug en dat wordt hier opgeslagen. Daarna
 * schrijft `payments:collect` af op dat mandaat — en vraagt bij elke ronde
 * opnieuw aan de provider of het nog geldig is.
 *
 * Eerder stond het klantkenmerk op de speler. Met orders die meerdere
 * kinderen bundelen betaalt één ouder; het mandaat hoort dus bij hem. De
 * speler-variant blijft werken voor wat er al liep.
 */
class Mandates
{
    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * Het klantkenmerk van deze ouder bij de provider; aanmaken als het er
     * nog niet is, met een mandaat in aanvraag.
     */
    public function customerReferenceFor(User $ouder): string
    {
        $bestaand = $ouder->mandates()->whereNotNull('customer_reference')->latest()->first();

        if ($bestaand !== null) {
            return $bestaand->customer_reference;
        }

        $kenmerk = $this->gateway->ensureCustomerFor($ouder);

        $ouder->mandates()->make([
            'provider' => 'mollie',
            'customer_reference' => $kenmerk,
            'status' => MandateStatus::Pending,
        ])->forceFill(['school_id' => $ouder->school_id])->save();

        return $kenmerk;
    }

    /** Het geldige mandaat van deze ouder, als dat er is. */
    public function validFor(User $ouder): ?Mandate
    {
        return $ouder->mandates()
            ->where('status', MandateStatus::Valid->value)
            ->whereNull('revoked_at')
            ->whereNotNull('mandate_reference')
            ->latest('valid_from')
            ->first();
    }

    /**
     * Wat de provider over een betaling zegt vastleggen: kwam er een mandaat
     * uit, dan is dat vanaf nu het mandaat van de betaler.
     */
    public function recordFromPayment(Payment $payment, RemotePayment $remote): ?Mandate
    {
        if ($remote->mandateReference === null) {
            return null;
        }

        $ouder = $payment->order?->user;

        if ($ouder === null) {
            return null;
        }

        $mandaat = $ouder->mandates()->where('mandate_reference', $remote->mandateReference)->first()
            ?? $ouder->mandates()->where('status', MandateStatus::Pending->value)->latest()->first()
            ?? $ouder->mandates()->make()->forceFill(['school_id' => $ouder->school_id]);

        $mandaat->fill([
            'provider' => 'mollie',
            'customer_reference' => $remote->customerReference ?? $mandaat->customer_reference,
            'mandate_reference' => $remote->mandateReference,
            'method' => $remote->method?->value ?? $mandaat->method,
            'status' => MandateStatus::Valid,
            'valid_from' => $mandaat->valid_from ?? now(),
            'revoked_at' => null,
        ])->save();

        return $mandaat;
    }

    /** De provider zegt dat het mandaat niet meer geldt. */
    public function revoke(Mandate $mandaat): void
    {
        $mandaat->fill(['status' => MandateStatus::Revoked, 'revoked_at' => now()])->save();
    }
}
