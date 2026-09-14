<?php

namespace App\Policies;

use App\Models\CourseAssessment;
use App\Models\Player;
use App\Models\User;

/**
 * Een begin- of eindniveau zie je als je het kind mag zien (ouder, kind,
 * trainer, eigenaar); vastleggen doet wie over dit kind mag schrijven.
 */
class CourseAssessmentPolicy
{
    public function view(User $user, CourseAssessment $assessment): bool
    {
        return $user->belongsToSameSchool($assessment) && $assessment->player !== null && $user->can('view', $assessment->player);
    }

    public function create(User $user, Player $player): bool
    {
        return $user->belongsToSameSchool($player) && ($user->isEigenaar() || $user->isTrainer()) && $user->can('createReport', $player);
    }
}
