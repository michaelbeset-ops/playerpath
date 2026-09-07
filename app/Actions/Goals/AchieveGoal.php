<?php

namespace App\Actions\Goals;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Notifications\DoelBehaald;
use Illuminate\Support\Facades\Notification;

/**
 * Een doel op behaald zetten en dat vieren.
 *
 * Eén plek, of het nu vanzelf gaat (EvaluateGoals, op basis van het cijfer) of
 * met de hand bij een eigen doel. Anders zou het ene doel wel een berichtje
 * naar de ouders opleveren en het andere niet, terwijl er voor een kind geen
 * verschil is tussen die twee.
 */
class AchieveGoal
{
    public function handle(Goal $goal): Goal
    {
        $goal->forceFill(['status' => GoalStatus::Achieved, 'achieved_at' => now()])->save();

        $player = $goal->player;
        $ontvangers = $player->guardians()->get()->all();

        if ($player->user) {
            $ontvangers[] = $player->user;
        }

        Notification::send($ontvangers, new DoelBehaald($goal, $player));

        return $goal;
    }
}
