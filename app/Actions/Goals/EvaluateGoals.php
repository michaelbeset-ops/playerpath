<?php

namespace App\Actions\Goals;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\Player;
use App\Notifications\DoelBehaald;
use Illuminate\Support\Facades\Notification;

/**
 * Na elk rapport: is een doel gehaald, of is de tijd verstreken?
 *
 * Gehaald is gehaald: het cijfer is op of boven het streefcijfer. Dat wordt
 * meteen vastgelegd en gevierd, ook als het later weer zakt — een gehaald doel
 * neem je een kind niet af.
 *
 * @return list<Goal> de doelen die nu gehaald zijn
 */
class EvaluateGoals
{
    public function handle(Player $player): array
    {
        $ratings = $player->category_ratings ?? [];
        $behaald = [];

        foreach ($player->goals()->active()->get() as $goal) {
            $huidig = $ratings[$goal->category->value] ?? null;

            if ($huidig !== null && $huidig >= $goal->target_rating) {
                $goal->forceFill(['status' => GoalStatus::Achieved, 'achieved_at' => now()])->save();
                $behaald[] = $goal;

                continue;
            }

            if ($goal->due_on->isPast()) {
                $goal->forceFill(['status' => GoalStatus::Missed])->save();
            }
        }

        if ($behaald !== []) {
            $ontvangers = $player->guardians()->get()->all();

            if ($player->user) {
                $ontvangers[] = $player->user;
            }

            foreach ($behaald as $goal) {
                Notification::send($ontvangers, new DoelBehaald($goal, $player));
            }
        }

        return $behaald;
    }
}
