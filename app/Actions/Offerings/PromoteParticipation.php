<?php

namespace App\Actions\Offerings;

use App\Actions\Payments\GeneratePayments;
use App\Actions\Products\SellProduct;
use App\Enums\ParticipationStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Participation;
use App\Models\Subscription;
use App\Notifications\PlekVrijgekomen;
use Illuminate\Support\Facades\DB;

/**
 * Iemand van de wachtlijst een plek geven.
 *
 * Hier ontstaat pas het geld. Op de wachtlijst staat niets open: een rekening
 * sturen voor een plek die er niet is, is precies het soort fout waar een
 * school een half jaar over hoort. Bij het doorschuiven ontstaat dus alsnog de
 * aankoop of het abonnement, met de eerste rekening.
 *
 * De school beslist wie er doorschuift. Automatisch de eerste van de lijst
 * pakken klinkt eerlijk, maar een school weet dingen die wij niet weten — dat
 * er al gebeld is, dat een gezin het inmiddels ergens anders heeft geregeld.
 */
class PromoteParticipation
{
    public function __construct(
        protected JoinOffering $deelname,
        protected SellProduct $verkoop,
        protected GeneratePayments $facturen,
    ) {}

    public function handle(Participation $participation): Participation
    {
        $aanbod = $participation->product;
        $speler = $participation->player;

        DB::transaction(function () use ($participation, $aanbod, $speler) {
            $aankoop = null;
            $abonnement = null;

            // Alleen als er nog niets betaald is. Iemand die al een plek had en
            // per ongeluk op de wachtlijst belandde, krijgt geen tweede rekening.
            if ($participation->purchase_id === null && $participation->subscription_id === null && $aanbod->amount_cents > 0) {
                if ($aanbod->isRecurring()) {
                    $abonnement = Subscription::create([
                        'player_id' => $speler->id,
                        'product_id' => $aanbod->id,
                        'amount_cents' => $aanbod->amount_cents,
                        'vat_rate' => $aanbod->vat_rate,
                        'interval' => $aanbod->interval,
                        'status' => SubscriptionStatus::Active,
                        'starts_on' => now()->toDateString(),
                        'ends_on' => $aanbod->stops_at_end ? $aanbod->ends_on : null,
                    ]);

                    $this->facturen->handle($abonnement);
                } else {
                    $aankoop = $this->verkoop->handle($speler, $aanbod);
                }
            }

            $this->deelname->handle(
                $aanbod,
                $speler,
                status: ParticipationStatus::Confirmed,
                purchase: $aankoop,
                subscription: $abonnement,
            );
        });

        // Pas na de transactie: een bericht over een plek die niet is
        // vastgelegd wil je niet versturen.
        $ontvangers = $speler->guardians()->get()->all();

        if ($speler->user) {
            $ontvangers[] = $speler->user;
        }

        foreach ($ontvangers as $ontvanger) {
            $ontvanger->notify(new PlekVrijgekomen($aanbod, $speler));
        }

        return $participation->refresh();
    }
}
