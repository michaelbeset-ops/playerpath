<?php

namespace App\Support\Goals;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\Player;

/**
 * Hoe staat een doel ervoor?
 *
 * "Op koers" is bewust simpel en uitlegbaar: de afgelegde weg (van start naar
 * streef) vergeleken met de verstreken tijd. Ben je op de helft van de tijd en
 * op de helft van de weg, dan lig je op koers. Dat begrijpt een ouder in één
 * zin, en dat is waar het om gaat.
 */
class GoalProgress
{
    /**
     * @return array{
     *   id: int, category: string, label: string, is_custom: bool, target: int|null,
     *   start: int|null, current: int|null, progress: int|null, expected: int,
     *   on_track: bool|null, status: string, status_label: string,
     *   due_on: string, days_left: int, note: string|null, achieved_at: string|null
     * }
     */
    public function describe(Goal $goal, Player $player): array
    {
        $totaalDagen = max(1, (int) $goal->starts_on->diffInDays($goal->due_on));
        $verstreken = max(0, min($totaalDagen, (int) $goal->starts_on->diffInDays(now()->startOfDay())));
        $verwacht = (int) round($verstreken / $totaalDagen * 100);

        $basis = [
            'id' => $goal->id,
            'category' => $goal->category,
            'label' => $goal->label(),
            'is_custom' => $goal->isCustom(),
            'status' => $goal->status->value,
            'status_label' => $goal->status->label(),
            'due_on' => $goal->due_on->format('d-m-Y'),
            'days_left' => max(0, (int) now()->startOfDay()->diffInDays($goal->due_on, false)),
            'note' => $goal->note,
            'achieved_at' => $goal->achieved_at?->format('d-m-Y'),
        ];

        // Een eigen doel heeft geen cijfer om aan af te meten. Dan is "op koers"
        // geen bescheiden schatting maar een verzinsel, en dus laten we het weg
        // in plaats van er een percentage bij te fantaseren.
        if ($goal->isCustom()) {
            return [
                ...$basis,
                // Een richtpunt mag erbij staan; meten doen we het niet.
                'target' => $goal->target_rating,
                'target_grade' => $goal->targetGrade(),
                'start' => null,
                'current' => null,
                'current_grade' => null,
                'progress' => null,
                'expected' => $verwacht,
                'on_track' => null,
                'track' => $goal->status === GoalStatus::Achieved ? 'achieved' : null,
                'track_label' => $goal->status === GoalStatus::Achieved ? 'Behaald' : null,
            ];
        }

        $huidig = ($player->category_ratings ?? [])[$goal->category] ?? null;

        $weg = max(1, $goal->target_rating - $goal->start_rating);
        $afgelegd = $huidig === null ? 0 : max(0, $huidig - $goal->start_rating);
        $voortgang = (int) min(100, round($afgelegd / $weg * 100));

        $behaald = $goal->status === GoalStatus::Achieved || ($huidig !== null && $huidig >= $goal->target_rating);
        $opKoers = $behaald || $voortgang >= $verwacht;

        // Drie woorden die een ouder snapt: behaald, op koers, net niet. En
        // "achter" als het echt niet bijloopt - meer dan vijftien punten onder
        // waar je nu hoort te zijn.
        $spoor = match (true) {
            $behaald => ['achieved', 'Behaald'],
            $opKoers => ['on_track', 'Op koers'],
            $voortgang >= $verwacht - 15 => ['just_behind', 'Net niet'],
            default => ['behind', 'Achter'],
        };

        return [
            ...$basis,
            'target' => $goal->target_rating,
            'target_grade' => $goal->targetGrade(),
            'start' => $goal->start_rating,
            'current' => $huidig === null ? null : (int) round($huidig),
            'current_grade' => $huidig === null ? null : Goal::gradeFromRating((int) round($huidig)),
            'progress' => $voortgang,
            'expected' => $verwacht,
            'on_track' => $opKoers,
            'track' => $spoor[0],
            'track_label' => $spoor[1],
        ];
    }

    /**
     * Alle doelen van een speler, actieve eerst.
     *
     * @return list<array<string, mixed>>
     */
    public function forPlayer(Player $player): array
    {
        return $player->goals()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('due_on')
            ->get()
            ->map(fn (Goal $goal) => $this->describe($goal, $player))
            ->all();
    }
}
