<?php

namespace App\Policies;

use App\Models\Player;
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

        if ($training->enrollments()->whereIn('player_id', $eigen)->active()->exists()) {
            return true;
        }

        // Een open training mag je bekijken als een van je kinderen erop past:
        // anders kun je niet zien waar je je op inschrijft.
        if ($this->enroll($user, $training)) {
            return true;
        }

        if ($training->group === null) {
            return in_array($training->slot?->player_id, $eigen, strict: true);
        }

        return $training->group
            ->players()
            ->whereIn('players.id', $eigen)
            ->exists();
    }

    /**
     * Los inschrijven doet de ouder, en alleen als de training openstaat en
     * minstens één van zijn kinderen erop past. De echte controle per kind
     * zit in EnrollInTraining; dit is de deur naar het scherm.
     */
    public function enroll(User $user, Training $training): bool
    {
        if (! $user->belongsToSameSchool($training) || ! $user->isOuder() || ! $training->isOpenForEnrollment()) {
            return false;
        }

        return Player::whereIn('id', $user->visiblePlayerIds())
            ->get()
            ->contains(fn (Player $kind) => $training->acceptsPlayer($kind)
                || $training->enrollments()->where('player_id', $kind->id)->active()->exists());
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
