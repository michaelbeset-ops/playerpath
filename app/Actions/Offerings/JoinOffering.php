<?php

namespace App\Actions\Offerings;

use App\Enums\ParticipationStatus;
use App\Models\Participation;
use App\Models\Player;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Subscription;

/**
 * Een speler laten meedoen aan een aanbod.
 *
 * Twee dingen tegelijk, en die horen bij elkaar:
 *
 * 1. **De deelname** (`participations`) met een status en de rekening die
 *    eraan hangt. Dat is waar capaciteit en de wachtlijst op rekenen.
 * 2. **De groep**, als het aanbod er een heeft. Daar hangen de trainingen
 *    onder, en dus ook de aanwezigheid en de rapporten. Zonder die stap staat
 *    een ingeschreven kind nergens op de deelnemerslijst van zijn eigen blok.
 *
 * Opnieuw aanmelden werkt de bestaande deelname bij in plaats van er een tweede
 * naast te zetten: dan zou één kind twee plekken bezet houden.
 */
class JoinOffering
{
    public function handle(
        Product $product,
        Player $player,
        ParticipationStatus $status = ParticipationStatus::Confirmed,
        ?Purchase $purchase = null,
        ?Subscription $subscription = null,
        ?int $enrollmentId = null,
    ): Participation {
        $deelname = Participation::firstOrNew([
            'product_id' => $product->id,
            'player_id' => $player->id,
        ]);

        $deelname->fill([
            'status' => $status,
            'purchase_id' => $purchase?->id ?? $deelname->purchase_id,
            'subscription_id' => $subscription?->id ?? $deelname->subscription_id,
            'enrollment_id' => $enrollmentId ?? $deelname->enrollment_id,
        ])->save();

        // Alleen wie echt meedoet komt in de groep. Iemand op de wachtlijst
        // hoort niet op de aanwezigheidslijst van de eerstvolgende training.
        if ($status === ParticipationStatus::Confirmed && $product->group !== null) {
            $player->groups()->syncWithoutDetaching([$product->group->id]);
        }

        return $deelname;
    }
}
