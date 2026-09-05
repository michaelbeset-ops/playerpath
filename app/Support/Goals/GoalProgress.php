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
     *   id: int, category: string, label: string, target: int, start: int, current: int|null,
     *   progress: int, expected: int, on_track: bool, status: string, status_label: string,
     *   due_on: string, days_left: int, note: string|null, achieved_at: string|null
     * }
     */
    public function describe(Goal $goal, Player $player): array
    {
        $huidig = ($player->category_ratings ?? [])[$goal->category->value] ?? null;

        $weg = max(1, $goal->target_rating - $goal->start_rating);
        $afgelegd = $huidig === null ? 0 : max(0, $huidig - $goal->start_rating);
        $voortgang = (int) min(100, round($afgelegd / $weg * 100));

        $totaalDagen = max(1, (int) $goal->starts_on->diffInDays($goal->due_on));
        $verstreken = max(0, min($totaalDagen, (int) $goal->starts_on->diffInDays(now()->startOfDay())));
        $verwacht = (int) round($verstreken / $totaalDagen * 100);

        $opKoers = $goal->status === GoalStatus::Achieved
            || ($huidig !== null && $huidig >= $goal->target_rating)
            || $voortgang >= $verwacht;

        return [
            'id' => $goal->id,
            'category' => $goal->category->value,
            'label' => $goal->category->label(),
            'target' => $goal->target_rating,
            'start' => $goal->start_rating,
            'current' => $huidig,
            'progress' => $voortgang,
            'expected' => $verwacht,
            'on_track' => $opKoers,
            'status' => $goal->status->value,
            'status_label' => $goal->status->label(),
            'due_on' => $goal->due_on->format('d-m-Y'),
            'days_left' => max(0, (int) now()->startOfDay()->diffInDays($goal->due_on, false)),
            'note' => $goal->note,
            'achieved_at' => $goal->achieved_at?->format('d-m-Y'),
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
