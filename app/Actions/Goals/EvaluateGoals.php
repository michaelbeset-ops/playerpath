<?php

namespace App\Actions\Goals;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\Player;

/**
 * Na elk rapport: is een doel gehaald, of is de tijd verstreken?
 *
 * Gehaald is gehaald: het cijfer is op of boven het streefcijfer. Dat wordt
 * meteen vastgelegd en gevierd, ook als het later weer zakt - een gehaald doel
 * neem je een kind niet af.
 *
 * Een eigen doel ("Uitverdedigen links") heeft geen cijfer om aan af te meten
 * en gaat hier dus nooit vanzelf op behaald; dat doet de trainer met de hand,
 * via AchieveGoal. Verlopen kan wel: de einddatum betekent voor elk doel
 * hetzelfde.
 *
 * @return list<Goal> de doelen die nu gehaald zijn
 */
class EvaluateGoals
{
    public function __construct(protected AchieveGoal $behalen) {}

    public function handle(Player $player): array
    {
        $ratings = $player->category_ratings ?? [];
        $behaald = [];

        foreach ($player->goals()->active()->get() as $goal) {
            $huidig = $goal->isCustom() ? null : ($ratings[$goal->category] ?? null);

            if ($huidig !== null && $huidig >= $goal->target_rating) {
                // Vastleggen en vieren staat op één plek, zodat een doel dat
                // met de hand wordt afgevinkt hetzelfde bericht oplevert.
                $behaald[] = $this->behalen->handle($goal);

                continue;
            }

            if ($goal->due_on->isPast()) {
                $goal->forceFill(['status' => GoalStatus::Missed])->save();
            }
        }

        return $behaald;
    }
}
