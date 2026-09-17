<?php

namespace App\Actions\Enrollments;

use App\Actions\Offerings\JoinOffering;
use App\Actions\Payments\GeneratePayments;
use App\Actions\Products\SellProduct;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PaymentOptionType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentOption;
use App\Models\Product;
use App\Models\Subscription;
use App\Notifications\InschrijvingGoedgekeurd;
use App\Support\Money\SplitAmount;
use Illuminate\Support\Facades\DB;

/**
 * Een inschrijving rond maken.
 *
 * Twee stappen die hier bij elkaar staan omdat ze elkaar opvolgen:
 *
 * 1. **`openOrConfirm()`** - de order gaat open: er ontstaan rekeningen (één
 *    per termijn, of één voor alles) en de inschrijving wacht op betaling.
 *    Valt er niets te betalen, dan wordt hij meteen bevestigd.
 * 2. **`confirm()`** - de inschrijving is rond: het kind komt in het aanbod
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
        // Niets te betalen, of al betaald (bijvoorbeeld: betaald, toen op de
        // wachtlijst beland omdat het vol zat, en nu alsnog uitgenodigd).
        if ($order === null || $order->total_cents <= 0 || $order->status === OrderStatus::Paid) {
            if ($order !== null && $order->status !== OrderStatus::Paid) {
                $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();
            }

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
     * Rekeningen voor een order.
     *
     * - **Per btw-tarief een eigen rekening** als de regels verschillende
     *   tarieven hebben (Order::amountsPerVatRate). Zo draagt elke rekening
     *   het tarief dat erbij hoort en klopt het omzetoverzicht ex btw.
     * - **Termijnen alleen als elk kind op de order dezelfde termijnregeling
     *   koos** (zelfde aantal, zelfde ritme). Kiest het ene kind drie termijnen
     *   en het andere ineens, dan is er geen eerlijk gedeeld schema; dan wordt
     *   het één rekening voor het geheel. De restcenten gaan naar de eerste
     *   termijnen, zodat de som exact klopt.
     *
     * Met `$bedrag` wordt alleen dat deel opnieuw opgemaakt (wat er nog open
     * staat nadat er al iets betaald is), over de termijnen die nog resten.
     *
     * Publiek omdat RemoveFromOrder ze opnieuw opmaakt als er een kind van een
     * gedeelde order af gaat.
     */
    public function maakRekeningen(Order $order, ?int $bedrag = null): void
    {
        $inschrijvingen = $order->enrollments()->with('paymentOption')->orderBy('id')->get()
            ->reject(fn (Enrollment $e) => in_array($e->status, [EnrollmentStatus::Cancelled, EnrollmentStatus::Declined, EnrollmentStatus::Expired], strict: true))
            ->values();
        $eerste = $inschrijvingen->first();

        // Alleen de kinderen met een regel op de order; een abonnement betaal je niet vooraf.
        $opties = $inschrijvingen->map(fn (Enrollment $e) => $e->paymentOption)
            ->filter(fn (?PaymentOption $o) => $o !== null && $o->type !== PaymentOptionType::Abonnement)
            ->values();
        $optie = $opties->first();
        $zelfdeTermijnen = $optie?->type === PaymentOptionType::Termijnen
            && $opties->every(fn (PaymentOption $o) => $o->type === PaymentOptionType::Termijnen
                && (int) $o->installments === (int) $optie->installments
                && $o->interval === $optie->interval);

        $termijnen = $zelfdeTermijnen ? max(1, (int) $optie->installments) : 1;
        $perWeek = $zelfdeTermijnen && $optie->interval === 'week';

        // Al betaalde termijnen komen niet opnieuw.
        $alBetaald = $bedrag === null ? 0 : $order->payments()->get()
            ->filter(fn (Payment $p) => $p->status->countsAsRevenue() && $p->installment_number !== null)
            ->pluck('installment_number')->unique()->count();
        $nog = max(1, $termijnen - $alBetaald);
        $vanaf = $termijnen > 1 ? min($alBetaald, $termijnen - 1) : 0;

        $teVerdelen = $bedrag ?? $order->total_cents;
        $perTarief = $order->amountsPerVatRate();

        if ($perTarief === [] || array_sum($perTarief) !== $teVerdelen) {
            $perTarief = SplitAmount::proportional($teVerdelen, array_map(fn (int $c) => max(0, $c), $perTarief ?: [21 => 1]));
        }

        $gemengd = count(array_filter($perTarief, fn (int $c) => $c > 0)) > 1;
        $aanbodregels = $order->lines()->where('type', '!=', 'discount')->get();
        $omschrijving = $aanbodregels->count() === 1
            ? $aanbodregels->first()->description
            : 'Inschrijving '.$inschrijvingen->pluck('first_name')->join(' en ');

        foreach ($perTarief as $tarief => $totaal) {
            if ($totaal <= 0) {
                continue;
            }

            $tekst = $gemengd ? "{$omschrijving} ({$tarief}% btw)" : $omschrijving;

            foreach (SplitAmount::into($totaal, $nog) as $index => $centen) {
                $nummer = $vanaf + $index + 1;

                Payment::create([
                    'player_id' => $eerste?->player_id,
                    'order_id' => $order->id,
                    'amount_cents' => $centen,
                    'vat_rate' => $tarief,
                    'status' => PaymentStatus::Open,
                    'method' => $eerste?->payment_method,
                    'description' => $termijnen > 1 ? "{$tekst} (termijn {$nummer} van {$termijnen})" : $tekst,
                    'due_on' => ($perWeek ? now()->addWeeks($index) : now()->addMonths($index))->toDateString(),
                    'installment_number' => $termijnen > 1 ? $nummer : null,
                    'installment_total' => $termijnen > 1 ? $termijnen : null,
                ]);
            }
        }
    }

    /**
     * Zijn alle plekken al echt vergeven? Zijn eigen plek telt niet mee, en
     * een kind dat al een bevestigde plek heeft neemt er geen nieuwe.
     *
     * Hier tellen alleen de bevestigde deelnemers, niet wie nog wacht. Bij het
     * aanmelden en uitnodigen houdt een wachtende een plek vast
     * (Product::spotsTaken); op het moment van bevestigen geldt: wie het eerst
     * rond is, heeft de plek. Anders kan iemand die al betaald heeft naar de
     * wachtlijst gaan voor een ander die nog niets deed.
     */
    protected function zitVol(Product $aanbod, Enrollment $enrollment): bool
    {
        if ($aanbod->capacity === null) {
            return false;
        }

        $bevestigd = $aanbod->participations()->confirmed();

        if ($enrollment->player_id !== null && (clone $bevestigd)->where('player_id', $enrollment->player_id)->exists()) {
            return false;
        }

        return $bevestigd->count() >= $aanbod->capacity;
    }

    /**
     * De inschrijving is rond: het kind doet mee.
     *
     * Eerst nog één keer tellen, met het aanbod op slot: twee betalingen die
     * tegelijk binnenkomen mogen niet allebei de laatste plek krijgen. Is het
     * inmiddels vol, dan gaat de inschrijving naar de wachtlijst in plaats van
     * bevestigd te worden. Wat er betaald is blijft staan; de school ziet hem
     * op de wachtlijst en beslist.
     */
    public function confirm(Enrollment $enrollment, bool $notify = true): Enrollment
    {
        $bevestigd = DB::transaction(function () use ($enrollment) {
            $aanbod = $enrollment->product_id !== null
                ? Product::query()->whereKey($enrollment->product_id)->lockForUpdate()->first()
                : null;
            $speler = $enrollment->player;
            $optie = $enrollment->paymentOption;

            if ($aanbod !== null && $this->zitVol($aanbod, $enrollment)) {
                $enrollment->transitionTo(EnrollmentStatus::Waitlist, ['waitlist' => true]);

                if ($speler !== null) {
                    $this->deelname->handle($aanbod, $speler, status: ParticipationStatus::Waitlist, enrollmentId: $enrollment->id);
                }

                return false;
            }

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

            return true;
        });

        if ($bevestigd && $notify && $enrollment->guardian !== null) {
            $enrollment->guardian->notify(new InschrijvingGoedgekeurd($enrollment->player, null, false));
        }

        return $enrollment;
    }
}
