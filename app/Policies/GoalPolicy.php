<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\Player;
use App\Models\User;

/**
 * Doelen stelt de trainer (of de eigenaar). Zien mag iedereen die de speler
 * mag zien — dat regelt PlayerPolicy::view.
 */
class GoalPolicy
{
    public function view(User $user, Goal $goal): bool
    {
        return $user->belongsToSameSchool($goal) && $user->can('view', $goal->player);
    }

    public function create(User $user): bool
    {
        return $user->isTrainer() || $user->isEigenaar();
    }

    public function createFor(User $user, Player $player): bool
    {
        return $user->belongsToSameSchool($player) && ($user->isTrainer() || $user->isEigenaar());
    }

    public function update(User $user, Goal $goal): bool
    {
        return $user->belongsToSameSchool($goal) && ($user->isTrainer() || $user->isEigenaar());
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $this->update($user, $goal);
    }
}
