<?php

namespace App\Policies;

use App\Models\EffortRating;
use App\Models\Player;
use App\Models\User;

/**
 * Inzetpunten zie je als je het kind mag zien; geven doet wie bij die
 * training mag afvinken en over dit kind mag schrijven. Eerst "zelfde
 * school", net als overal.
 */
class EffortRatingPolicy
{
    public function view(User $user, EffortRating $rating): bool
    {
        return $user->belongsToSameSchool($rating) && $rating->player !== null && $user->can('view', $rating->player);
    }

    public function create(User $user, Player $player): bool
    {
        return $user->belongsToSameSchool($player) && $user->can('createReport', $player);
    }
}
