<?php

namespace App\Actions\Trainings;

use App\Enums\AttendanceStatus;
use App\Models\EffortRating;
use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Support\Rating\RatingEngine;
use Illuminate\Support\Facades\DB;

/**
 * Na de training: aanwezig, inzet, houding en een notitie voor één kind.
 *
 * De enige plek waar inzetpunten ontstaan. Aanwezig levert de basispunten
 * (RecordAttendance), de gekozen treden van inzet en houding komen erbij.
 * Er bestaat geen negatieve trede: niets kiezen is nul extra, nooit minder.
 *
 * Opnieuw opslaan vervangt de vorige keuze voor die training; de XP-boeking
 * gaat mee, zodat twee keer opslaan nooit dubbele punten oplevert. Punten
 * dalen alleen door zo'n correctie van de trainer, nooit door een mindere dag.
 */
class RecordEffort
{
    public function __construct(protected RecordAttendance $attendance, protected RatingEngine $engine) {}

    public function handle(Training $training, Player $player, bool $present, ?string $effort, ?string $attitude, ?string $note, ?User $door = null): ?EffortRating
    {
        return DB::transaction(function () use ($training, $player, $present, $effort, $attitude, $note, $door) {
            // Afwezig ruimt de inzetpunten van deze training zelf op.
            $this->attendance->handle($training, $player, $present ? AttendanceStatus::Present : AttendanceStatus::Absent);

            if (! $present) {
                return null;
            }

            $settings = $this->engine->settingsFor($player);
            $inzet = $settings->effortLevel($effort);
            $houding = $settings->attitudeLevel($attitude);
            $notitie = trim((string) $note);

            $rating = EffortRating::query()->updateOrCreate(
                ['training_id' => $training->id, 'player_id' => $player->id],
                [
                    'effort' => $inzet['key'] ?? null,
                    'attitude' => $houding['key'] ?? null,
                    'effort_points' => $inzet['points'] ?? 0,
                    'attitude_points' => $houding['points'] ?? 0,
                    'note' => $notitie === '' ? null : $notitie,
                    'rated_by' => $door?->id,
                ],
            );

            $this->engine->revoke($player, 'inzet', $rating);

            $labels = array_values(array_filter([$inzet['label'] ?? null, $houding['label'] ?? null]));

            $this->engine->award(
                $player,
                'inzet',
                $rating->points(),
                'Inzet bij '.$training->label().($labels === [] ? '' : ': '.implode(', ', $labels)),
                $rating,
                $training->starts_at,
            );

            return $rating;
        });
    }
}
