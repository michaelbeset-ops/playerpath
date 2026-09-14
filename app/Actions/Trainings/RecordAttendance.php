<?php

namespace App\Actions\Trainings;

use App\Actions\Products\ConsumeCredit;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\EffortRating;
use App\Models\Player;
use App\Models\Training;
use App\Support\Rating\RatingEngine;

/**
 * Aanwezig of afwezig vastleggen, met alles wat eraan hangt.
 *
 * Eén plek voor de aanwezigheidslijst en de inzetflow, zodat een beurt van de
 * rittenkaart en de XP nooit op twee manieren geboekt worden.
 */
class RecordAttendance
{
    public function __construct(protected ConsumeCredit $credits, protected RatingEngine $engine) {}

    public function handle(Training $training, Player $player, ?AttendanceStatus $status): Attendance
    {
        $aanwezigheid = $training->attendances()->updateOrCreate(
            ['player_id' => $player->id],
            ['status' => $status?->value],
        );

        // Een beurt gaat van de rittenkaart zodra iemand aanwezig gemeld wordt,
        // en komt terug als de trainer zich vergist. Heeft de speler geen kaart,
        // dan gebeurt hier niets: afvinken mag nooit stuklopen op de
        // administratie.
        $this->credits->sync($aanwezigheid, $status);

        // Aanwezig zijn is XP: trouw komen wordt beloond, niet alleen talent.
        // Een vergissing van de trainer draait de punten weer terug.
        if ($status === AttendanceStatus::Present) {
            $this->engine->award(
                $player,
                'attendance',
                $this->engine->settingsFor($player)->xpForAttendance(),
                'Aanwezig bij '.$training->label(),
                $aanwezigheid,
                $training->starts_at,
            );

            return $aanwezigheid;
        }

        $this->engine->revoke($player, 'attendance', $aanwezigheid);

        // Niet aanwezig is ook geen inzet: de inzetpunten gaan mee terug.
        $inzet = EffortRating::query()->where('training_id', $training->id)->where('player_id', $player->id)->first();

        if ($inzet !== null) {
            $this->engine->revoke($player, 'inzet', $inzet);
            $inzet->delete();
        }

        return $aanwezigheid;
    }
}
