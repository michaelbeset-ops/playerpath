<?php

namespace App\Actions\Offerings;

use App\Actions\Products\SellProduct;
use App\Models\Player;
use App\Models\Slot;
use App\Models\Training;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Een privétraining boeken op een beschikbaar moment.
 *
 * Drie dingen gebeuren hier tegelijk, en die horen bij elkaar:
 *
 * 1. **Het moment gaat op naam.** Vanaf dat moment is het bezet; een tweede
 *    kind op hetzelfde uur bij dezelfde trainer is geen privétraining meer.
 * 2. **Er ontstaat een echte training**, met de trainer erbij. Daardoor staat
 *    dit uur in de agenda en bij Mijn trainingen, en kan er gewoon aanwezigheid
 *    en een rapport bij — precies zoals bij elke andere training.
 * 3. **De rekening**, via dezelfde weg als de rest (SellProduct). Gratis is
 *    geen rekening; een kennismakingsles van nul euro hoort niet in het
 *    openstaande-overzicht.
 *
 * Het boeken zelf staat in een transactie met een verse controle op bezet: twee
 * ouders die tegelijk op dezelfde knop drukken mogen niet allebei dat uur
 * krijgen.
 */
class BookSlot
{
    public function __construct(protected SellProduct $verkoop) {}

    public function handle(Slot $slot, Player $player): Slot
    {
        return DB::transaction(function () use ($slot, $player) {
            // Vers ophalen en op slot zetten: tussen het tonen van de lijst en
            // deze klik kan iemand anders er al geweest zijn.
            $vers = Slot::whereKey($slot->id)->lockForUpdate()->firstOrFail();

            if ($vers->isTaken()) {
                throw new RuntimeException('Dit moment is net geboekt. Kies een ander moment.');
            }

            $aanbod = $vers->product;
            $aankoop = $aanbod->amount_cents > 0 ? $this->verkoop->handle($player, $aanbod) : null;

            $vers->forceFill([
                'player_id' => $player->id,
                'purchase_id' => $aankoop?->id,
                'booked_at' => now(),
            ])->save();

            $training = Training::create([
                'group_id' => null,
                'slot_id' => $vers->id,
                'starts_at' => $vers->starts_at,
                'ends_at' => $vers->ends_at,
                'location' => $vers->location ?? $aanbod->location,
            ]);

            if ($vers->user_id !== null) {
                $training->trainers()->sync([$vers->user_id]);
            }

            return $vers;
        });
    }

    /**
     * Een boeking terugdraaien: het moment komt weer vrij.
     *
     * De rekening blijft staan. Wat er is afgesproken hoort in de historie te
     * blijven; of er iets terugbetaald wordt is een gesprek tussen de school en
     * de ouder, geen automatische boeking.
     */
    public function release(Slot $slot): Slot
    {
        DB::transaction(function () use ($slot) {
            $slot->training?->delete();

            $slot->forceFill([
                'player_id' => null,
                'purchase_id' => null,
                'enrollment_id' => null,
                'booked_at' => null,
            ])->save();
        });

        return $slot->refresh();
    }
}
