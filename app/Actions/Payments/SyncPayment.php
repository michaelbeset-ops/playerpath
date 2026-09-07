<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Notifications\BetalingOntvangen;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Payments\Mandates;
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
    public function __construct(protected SettleOrder $order, protected Mandates $mandates) {}

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

        // Een betaling die bij een inschrijving hoort: de order en de
        // inschrijving volgen de stand.
        $this->order->handle($payment);

        // Kwam er een mandaat uit (eerste iDEAL-betaling): dat is nu het
        // mandaat van de betaler.
        $this->mandates->recordFromPayment($payment, $remote);

        // Een abonnement volgt zijn betaling: mislukt zet hem op "betaling
        // mislukt", betaald zet hem weer op actief.
        $this->abonnementVolgt($payment);

        // Gestorneerd: de school mag daar kosten voor rekenen (instelling).
        if ($payment->status === PaymentStatus::ChargedBack) {
            $this->storneringskosten($payment);
        }

        return $payment;
    }

    private function abonnementVolgt(Payment $payment): void
    {
        $abonnement = $payment->subscription;

        if ($abonnement === null) {
            return;
        }

        if ($payment->status->needsAttention() && $abonnement->status->canTransitionTo(SubscriptionStatus::PaymentFailed)) {
            $abonnement->transitionTo(SubscriptionStatus::PaymentFailed);
        } elseif ($payment->status === PaymentStatus::Paid && $abonnement->status === SubscriptionStatus::PaymentFailed) {
            $abonnement->transitionTo(SubscriptionStatus::Active);
        }
    }

    /**
     * Storneringskosten, één keer per gestorneerde betaling. Een eigen
     * rekening met `parent_id`, zodat je later ziet waar hij vandaan kwam.
     */
    private function storneringskosten(Payment $payment): void
    {
        $kosten = EnrollmentSettings::for($payment->school)->chargebackFeeCents();

        if ($kosten <= 0 || Payment::where('parent_id', $payment->id)->exists()) {
            return;
        }

        Payment::create([
            'player_id' => $payment->player_id,
            'order_id' => $payment->order_id,
            'parent_id' => $payment->id,
            'amount_cents' => $kosten,
            'vat_rate' => 21,
            'status' => PaymentStatus::Open,
            'method' => $payment->method,
            'description' => 'Storneringskosten',
            'due_on' => now()->addDays(14)->toDateString(),
        ]);
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
