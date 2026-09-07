<?php

namespace App\Policies;

use App\Models\Training;
use App\Models\User;

/**
 * Trainingen plannen doen de eigenaar en de trainer; verwijderen alleen de
 * eigenaar, want dat neemt de aanwezigheidshistorie mee.
 *
 * Kijken mag iedereen binnen de school — welke trainingen je te zien krijgt
 * is een aparte vraag, en die beantwoordt VisibleTrainings.
 */
class TrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->school_id !== null;
    }

    public function view(User $user, Training $training): bool
    {
        if (! $user->belongsToSameSchool($training)) {
            return false;
        }

        if ($user->isEigenaar() || $user->isTrainer()) {
            return true;
        }

        // Speler en ouder zien alleen trainingen van een groep waar hun eigen
        // speler in zit — of hun eigen privétraining, want die heeft geen groep.
        $eigen = $user->visiblePlayerIds();

        if ($training->group === null) {
            return in_array($training->slot?->player_id, $eigen, strict: true);
        }

        return $training->group
            ->players()
            ->whereIn('players.id', $eigen)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    public function update(User $user, Training $training): bool
    {
        return $user->belongsToSameSchool($training)
            && ($user->isEigenaar() || $user->isTrainer());
    }

    public function delete(User $user, Training $training): bool
    {
        return $user->belongsToSameSchool($training) && $user->isEigenaar();
    }

    /** Aanwezigheid afvinken is werk van de trainer. */
    public function recordAttendance(User $user, Training $training): bool
    {
        return $user->belongsToSameSchool($training)
            && ($user->isEigenaar() || $user->isTrainer());
    }
}
