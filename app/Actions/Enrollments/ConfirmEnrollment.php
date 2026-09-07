<?php

namespace App\Actions\Enrollments;

use App\Actions\Offerings\JoinOffering;
use App\Actions\Payments\GeneratePayments;
use App\Actions\Products\SellProduct;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentOptionType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\InschrijvingGoedgekeurd;
use App\Support\Money\SplitAmount;
use Illuminate\Support\Facades\DB;

/**
 * Een inschrijving rond maken.
 *
 * Twee stappen die hier bij elkaar staan omdat ze elkaar opvolgen:
 *
 * 1. **`openOrConfirm()`** — de order gaat open: er ontstaan rekeningen (één
 *    per termijn, of één voor alles) en de inschrijving wacht op betaling.
 *    Valt er niets te betalen, dan wordt hij meteen bevestigd.
 * 2. **`confirm()`** — de inschrijving is rond: het kind komt in het aanbod
 *    en in de groep (JoinOffering), en bij een abonnement ontstaat het
 *    abonnement met zijn eigen termijnen. Aankopen ontstaan zonder eigen
 *    rekening: die zit al op de order.
 *
 * De rekeningen van de order en van het abonnement zijn twee verschillende
 * dingen. De order is wat je nu betaalt (eenmalig, termijnen, inschrijfgeld);
 * het abonnement brengt daarna zelf elke periode een rekening voort.
 */
class ConfirmEnrollment
{
    public function __construct(
        protected JoinOffering $deelname,
        protected SellProduct $verkoop,
        protected GeneratePayments $facturen,
    ) {}

    /** De order open zetten (rekeningen maken), of meteen bevestigen als er niets te betalen valt. */
    public function openOrConfirm(Enrollment $enrollment, ?Order $order): void
    {
        if ($order === null || $order->total_cents <= 0) {
            $order?->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();
            $this->confirm($enrollment);

            return;
        }

        if ($order->status === OrderStatus::Concept) {
            $this->maakRekeningen($order);
            $order->forceFill(['status' => OrderStatus::Open])->save();
        }

        $enrollment->transitionTo(EnrollmentStatus::AwaitingPayment);
    }

    /**
     * Rekeningen voor een order: één per termijn als de standaardregel in
     * termijnen is, anders één voor het geheel. De restcenten gaan naar de
     * eerste termijnen, zodat de som exact klopt.
     */
    protected function maakRekeningen(Order $order): void
    {
        $eerste = $order->enrollments()->with('paymentOption')->first();
        $optie = $eerste?->paymentOption;
        $termijnen = $optie?->type === PaymentOptionType::Termijnen ? max(1, (int) $optie->installments) : 1;
        $perWeek = $optie?->interval === 'week';
        $bedragen = SplitAmount::into($order->total_cents, $termijnen);
        $omschrijving = $order->lines()->where('type', '!=', 'discount')->count() === 1
            ? $order->lines()->first()->description
            : 'Inschrijving '.$order->enrollments()->pluck('first_name')->join(' en ');

        foreach ($bedragen as $index => $centen) {
            Payment::create([
                'player_id' => $eerste?->player_id,
                'order_id' => $order->id,
                'amount_cents' => $centen,
                'vat_rate' => 21,
                'status' => PaymentStatus::Open,
                'method' => $eerste?->payment_method,
                'description' => $termijnen > 1 ? "{$omschrijving} (termijn ".($index + 1)." van {$termijnen})" : $omschrijving,
                'due_on' => ($perWeek ? now()->addWeeks($index) : now()->addMonths($index))->toDateString(),
                'installment_number' => $termijnen > 1 ? $index + 1 : null,
                'installment_total' => $termijnen > 1 ? $termijnen : null,
            ]);
        }
    }

    /** De inschrijving is rond: het kind doet mee. */
    public function confirm(Enrollment $enrollment, bool $notify = true): Enrollment
    {
        DB::transaction(function () use ($enrollment) {
            $aanbod = $enrollment->product;
            $speler = $enrollment->player;
            $optie = $enrollment->paymentOption;

            if ($aanbod !== null && $speler !== null) {
                $abonnement = null;
                $aankoop = null;

                if ($optie?->type === PaymentOptionType::Abonnement) {
                    $abonnement = Subscription::create([
                        'player_id' => $speler->id,
                        'product_id' => $aanbod->id,
                        'payment_option_id' => $optie->id,
                        'enrollment_id' => $enrollment->id,
                        'amount_cents' => $optie->amount_cents,
                        'vat_rate' => $aanbod->vat_rate,
                        'interval' => $optie->interval ?? 'monthly',
                        'status' => SubscriptionStatus::Active,
                        'payment_method' => $enrollment->payment_method,
                        'starts_on' => now()->toDateString(),
                        'ends_on' => $aanbod->stops_at_end ? $aanbod->ends_on : null,
                    ]);

                    $this->facturen->handle($abonnement);
                } elseif (! $aanbod->isRecurring()) {
                    // De rekening zit op de order; de aankoop is de afspraak.
                    $aankoop = $this->verkoop->handle($speler, $aanbod, withPayment: false);
                }

                $this->deelname->handle($aanbod, $speler, purchase: $aankoop, subscription: $abonnement, enrollmentId: $enrollment->id);
            }

            $enrollment->transitionTo(EnrollmentStatus::Confirmed, ['confirmed_at' => now()]);
        });

        if ($notify && $enrollment->guardian !== null) {
            $enrollment->guardian->notify(new InschrijvingGoedgekeurd($enrollment->player, null, false));
        }

        return $enrollment;
    }
}
