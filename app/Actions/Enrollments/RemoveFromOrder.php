<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\OrderLineType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;

/**
 * Eén kind van een (gedeelde) order halen: bij afwijzen en bij annuleren.
 *
 * Eén order kan meerdere kinderen bundelen. Alleen dít kind gaat eraf: zijn
 * regels verdwijnen, de korting krimpt naar rato mee (hij ging over het
 * aanbod, en een deel daarvan is weg), en het totaal wordt opnieuw berekend.
 * Blijft er geen ander kind over dat nog meedoet of wacht, dan gaat de hele
 * order dicht - ook de regels per gezin (inschrijfgeld, kledingpakket)
 * hebben dan geen reden meer.
 *
 * Openstaande rekeningen op het oude totaal kloppen daarna niet meer: die
 * vervallen en worden opnieuw opgemaakt voor wat er nog open staat. Is er al
 * iets betaald, dan telt dat mee, min wat er voor geannuleerde kinderen is
 * teruggegeven. Wat al betaald is blijft staan: dat is de historie.
 *
 * Wat een geannuleerd kind aan restitutie krijgt moet vóór deze stap op de
 * inschrijving staan (`refund_cents`); daarna zijn zijn regels weg.
 */
class RemoveFromOrder
{
    public function __construct(protected ConfirmEnrollment $bevestig) {}

    public function handle(Order $order, Enrollment $enrollment): void
    {
        $aanbodVoor = $this->aanbod($order);
        $order->lines()->where('enrollment_id', $enrollment->id)->delete();
        $aanbodNa = $this->aanbod($order);

        // De korting ging over het aanbod: naar rato mee omlaag.
        if ($aanbodVoor > 0 && $aanbodNa < $aanbodVoor) {
            $order->lines()->where('type', OrderLineType::Discount->value)->get()
                ->each(function (OrderLine $korting) use ($aanbodVoor, $aanbodNa) {
                    $nieuw = intdiv(abs((int) $korting->amount_cents) * $aanbodNa, $aanbodVoor);

                    $nieuw === 0
                        ? $korting->delete()
                        : $korting->forceFill(['amount_cents' => -$nieuw])->save();
                });
        }

        $anderen = $order->enrollments()->whereKeyNot($enrollment->id)->get()
            ->filter(fn (Enrollment $e) => $e->status->isOpen() || $e->status->isSettled());

        $openstaand = $order->payments()->outstanding()->get()
            ->filter(fn (Payment $p) => $p->canTransitionTo(PaymentStatus::Cancelled));
        $ontvangen = (int) $order->payments()->get()
            ->filter(fn (Payment $p) => $p->status->countsAsRevenue())
            ->sum('amount_cents');

        if ($anderen->isEmpty()) {
            $openstaand->each(fn (Payment $p) => $p->transitionTo(PaymentStatus::Cancelled));
            $order->lines()->delete();
            $order->recalculate();
            $order->forceFill(['status' => $ontvangen > 0 ? OrderStatus::Paid : OrderStatus::Cancelled])->save();

            return;
        }

        $order->recalculate();

        if ($order->status !== OrderStatus::Open || $openstaand->isEmpty()) {
            return;
        }

        $openstaand->each(fn (Payment $p) => $p->transitionTo(PaymentStatus::Cancelled));

        if ($ontvangen === 0) {
            if ($order->total_cents > 0) {
                $this->bevestig->maakRekeningen($order);
            }

            return;
        }

        $teruggegeven = (int) $order->enrollments()
            ->where('status', EnrollmentStatus::Cancelled->value)
            ->sum('refund_cents');
        $nog = $order->total_cents - max(0, $ontvangen - $teruggegeven);

        if ($nog > 0) {
            $this->bevestig->maakRekeningen($order, $nog);
        } else {
            $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => $order->paid_at ?? now()])->save();
        }
    }

    protected function aanbod(Order $order): int
    {
        return (int) $order->lines()
            ->whereIn('type', [OrderLineType::Offering->value, OrderLineType::Trial->value])
            ->sum('amount_cents');
    }
}
