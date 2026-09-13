<?php

namespace App\Support\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Een betaalprovider die doet alsof - voor demo's en om de flow te laten zien.
 *
 * Er wordt geen cent verplaatst. In plaats van naar iDEAL gaat de betaler
 * naar een eigen scherm (`/betalen/demo/{payment}`) dat op een bankkeuze
 * lijkt, met "DEMO" er groot op, en een knop "Betaal" of "Annuleer". Wat hij
 * daar kiest wordt via `SyncPayment` verwerkt, precies zoals een webhook van
 * Mollie dat zou doen - dus de rest van de app (order, inschrijving, mandaat,
 * abonnement, mails) werkt zonder er iets van te weten.
 *
 * Alleen aan als `PAYMENTS_DEMO=true` én er geen Mollie-sleutel is; zie
 * `AppServiceProvider`. Op productie weigert `playerpath:check` het.
 *
 * De stand van een betaling wordt niet ergens apart bewaard: `fetch()` leest
 * hem uit de betaling zelf. Dat is precies wat er na het demoscherm in staat.
 */
class DemoGateway implements PaymentGateway
{
    public function isConnected(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'Demo-betalingen';
    }

    public function statusMessage(): string
    {
        return 'Betalingen worden nagebootst: je ziet de hele flow, maar er wordt geen geld afgeschreven.';
    }

    public function start(Payment $payment, string $returnUrl, string $webhookUrl, ?string $customerReference = null): RemotePayment
    {
        $kenmerk = 'demo_'.Str::lower(Str::random(12));

        // Waar de betaler heen moet: ons eigen scherm, ondertekend, met de
        // terugweg erin. Een mandaat (abonnement) staat er ook in, zodat het
        // scherm kan zeggen dat je een machtiging afgeeft.
        $checkout = URL::temporarySignedRoute('demo-pay.show', now()->addDay(), [
            'payment' => $payment->id,
            'terug' => $returnUrl,
            'mandaat' => $customerReference !== null ? 1 : 0,
        ]);

        return new RemotePayment(
            reference: $kenmerk,
            status: PaymentStatus::Open,
            checkoutUrl: $checkout,
            customerReference: $customerReference,
        );
    }

    public function fetch(string $reference): RemotePayment
    {
        $betaling = Payment::withoutSchoolScope()->where('external_reference', $reference)->first();

        return new RemotePayment(
            reference: $reference,
            status: $betaling?->status ?? PaymentStatus::Open,
            paidAt: $betaling?->paid_at === null ? null : CarbonImmutable::instance($betaling->paid_at),
            method: $betaling?->method,
        );
    }

    public function ensureCustomer(Player $player): string
    {
        return 'cst_demo_speler_'.$player->id;
    }

    public function ensureCustomerFor(User $user): string
    {
        return 'cst_demo_'.$user->id;
    }

    public function hasValidMandate(string $customerReference): bool
    {
        return true;
    }

    /** Een incasso slaagt in de demo altijd; ook dat gaat via SyncPayment. */
    public function charge(Payment $payment, string $customerReference, string $webhookUrl): RemotePayment
    {
        return new RemotePayment(
            reference: 'demo_'.Str::lower(Str::random(12)),
            status: PaymentStatus::Paid,
            paidAt: CarbonImmutable::now(),
            method: PaymentMethod::DirectDebit,
            customerReference: $customerReference,
            mandateReference: 'mdt_demo_'.$customerReference,
        );
    }
}
