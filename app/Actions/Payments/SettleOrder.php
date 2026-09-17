<?php

namespace App\Actions\Payments;

use App\Actions\Enrollments\ConfirmEnrollment;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

/**
 * Wat een betaalstand met de order en de inschrijvingen doet.
 *
 * Eén plek, aangeroepen door alles wat een betaling op een stand zet (de
 * webhook via SyncPayment én het handmatig op betaald zetten):
 *
 * - **de eerste betaling is binnen** → de inschrijvingen zijn bevestigd. Bij
 *   termijnen wacht je niet tot de laatste: het kind doet mee zodra de eerste
 *   termijn betaald is, precies zoals bij een gewone afspraak.
 * - **alle betalingen zijn binnen** → de order is betaald.
 * - **een betaling mislukt of verloopt** → de inschrijving staat op "betaling
 *   mislukt", zodat het in de inbox opvalt en er een nieuwe link uit kan.
 */
class SettleOrder
{
    public function __construct(protected ConfirmEnrollment $bevestig) {}

    public function handle(Payment $payment): void
    {
        $order = $payment->order;

        if ($order === null) {
            return;
        }

        $order->load('payments', 'enrollments');

        if ($payment->status === PaymentStatus::Paid) {
            foreach ($order->enrollments as $inschrijving) {
                if (in_array($inschrijving->status, [EnrollmentStatus::AwaitingPayment, EnrollmentStatus::PaymentFailed, EnrollmentStatus::Expired], strict: true)) {
                    $this->bevestig->confirm($inschrijving);
                }
            }

            // Een geannuleerde rekening (bijvoorbeeld vervangen nadat een kind
            // van de order ging) hoeft niet meer betaald te worden.
            $allesBetaald = $order->payments
                ->reject(fn (Payment $p) => $p->status === PaymentStatus::Cancelled)
                ->every(fn (Payment $p) => $p->status->countsAsRevenue());

            if ($allesBetaald) {
                $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();
            }

            return;
        }

        if ($payment->status->needsAttention()) {
            foreach ($order->enrollments as $inschrijving) {
                if ($inschrijving->status === EnrollmentStatus::AwaitingPayment) {
                    $inschrijving->transitionTo(EnrollmentStatus::PaymentFailed);
                }
            }
        }
    }

    /** Alles opnieuw beoordelen, bijvoorbeeld na een handmatige correctie. */
    public function reassess(Order $order): void
    {
        $laatste = $order->payments()->latest('updated_at')->first();

        if ($laatste !== null) {
            $this->handle($laatste);
        }
    }
}
