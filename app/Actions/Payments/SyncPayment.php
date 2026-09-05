<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\BetalingOntvangen;
use App\Support\Payments\RemotePayment;
use Illuminate\Support\Facades\Notification;

/**
 * De stand van de provider op onze betaling zetten.
 *
 * Eén plek, want dit gebeurt vanuit drie kanten: de webhook, de terugkeer uit
 * de browser, en het handmatig ophalen door de eigenaar. Zou elk daarvan het
 * zelf doen, dan lopen ze gegarandeerd uit de pas.
 *
 * De actie is bewust idempotent: dezelfde webhook twee keer verwerken levert
 * niet twee bevestigingsmails op. Mollie herhaalt webhooks bij twijfel, dus
 * dat is geen theoretisch geval.
 */
class SyncPayment
{
    public function handle(Payment $payment, RemotePayment $remote): Payment
    {
        $wasBetaald = $payment->status === PaymentStatus::Paid;

        $payment->forceFill([
            'external_reference' => $remote->reference,
            'status' => $remote->status,
            'paid_at' => $remote->status === PaymentStatus::Paid
                ? ($remote->paidAt ?? $payment->paid_at ?? now())
                : null,
            // De methode alleen overnemen als de provider er een noemt: bij een
            // openstaande betaling weet die nog niet waarmee er betaald wordt,
            // en dan is een handmatig gezette methode beter dan leeg.
            'method' => $remote->method ?? $payment->method,
        ])->save();

        if (! $wasBetaald && $payment->status === PaymentStatus::Paid) {
            $this->bevestig($payment);
        }

        return $payment;
    }

    private function bevestig(Payment $payment): void
    {
        $player = $payment->player;

        if ($player === null) {
            return;
        }

        $ontvangers = $player->guardians;

        if ($ontvangers->isEmpty()) {
            return;
        }

        Notification::send($ontvangers, new BetalingOntvangen($payment));
    }
}
