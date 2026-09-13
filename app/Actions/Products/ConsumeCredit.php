<?php

namespace App\Actions\Products;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

/**
 * Beurten van een rittenkaart afschrijven, en teruggeven.
 *
 * Een rittenkaart zonder afschrijven is een prijslijst, geen kaart. De beurt
 * gaat eraf op het moment dat de trainer iemand **aanwezig** meldt - niet bij
 * het aanmelden. Wie zich aanmeldt en niet komt heeft niets afgenomen, en dat
 * verschil tussen "ik kom" en "hij was er" is precies waarom `registration` en
 * `status` in dit project twee losse velden zijn.
 *
 * Drie eigenschappen die je niet moet weghalen:
 *
 * 1. **De oudste bruikbare kaart gaat eerst.** Die verloopt als eerste;
 *    andersom raakt een ouder beurten kwijt die hij had kunnen gebruiken.
 * 2. **Het is omkeerbaar.** `attendances.purchase_id` legt vast van welke kaart
 *    de beurt kwam, dus een trainer die zich vergist krijgt hem terug op
 *    dezelfde kaart - ook als die inmiddels verlopen is.
 * 3. **Zonder kaart gebeurt er niets.** Een school die geen rittenkaarten
 *    verkoopt merkt hier niets van, en een speler zonder saldo wordt gewoon
 *    afgevinkt. Aanwezigheid vastleggen mag nooit stuklopen op de administratie.
 */
class ConsumeCredit
{
    /**
     * De aanwezigheid bijwerken en het saldo laten volgen.
     *
     * Geef de nieuwe status door; de rest volgt daaruit.
     */
    public function sync(Attendance $attendance, ?AttendanceStatus $status): void
    {
        DB::transaction(function () use ($attendance, $status) {
            $wasAfgeschreven = $attendance->purchase_id !== null;
            $moetAfschrijven = $status === AttendanceStatus::Present;

            if ($wasAfgeschreven && ! $moetAfschrijven) {
                $this->geefTerug($attendance);

                return;
            }

            if (! $wasAfgeschreven && $moetAfschrijven) {
                $this->schrijfAf($attendance);
            }
        });
    }

    protected function schrijfAf(Attendance $attendance): void
    {
        $kaart = Purchase::usable()->where('player_id', $attendance->player_id)->first();

        if ($kaart === null) {
            return;
        }

        $kaart->increment('credits_used');
        $attendance->forceFill(['purchase_id' => $kaart->id])->save();

        // Op is op: de kaart blijft staan als bewijs van wat er is afgenomen,
        // maar telt niet meer mee als bruikbaar saldo.
        if ($kaart->fresh()->creditsLeft() === 0) {
            $kaart->update(['status' => 'used']);
        }
    }

    protected function geefTerug(Attendance $attendance): void
    {
        $kaart = Purchase::withoutSchoolScope()->find($attendance->purchase_id);

        $attendance->forceFill(['purchase_id' => null])->save();

        if ($kaart === null) {
            return;
        }

        if ($kaart->credits_used > 0) {
            $kaart->decrement('credits_used');
        }

        // Was hij op en daarom gesloten, dan gaat hij weer open.
        if ($kaart->status === 'used') {
            $kaart->update(['status' => 'active']);
        }
    }
}
