<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Support\Trainers\TrainerScope;

/**
 * Trainingen plannen doen de eigenaar en de trainer; verwijderen alleen de
 * eigenaar, want dat neemt de aanwezigheidshistorie mee.
 *
 * Kijken mag iedereen binnen de school - welke trainingen je te zien krijgt
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
        // speler in zit - of hun eigen privétraining, want die heeft geen groep.
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

    /**
     * Een kind afmelden van een losse training.
     *
     * Ruimer dan inschrijven: ook als de school los inschrijven inmiddels heeft
     * uitgezet, moet een ouder zijn kind kunnen afmelden. Alleen de ouder, niet
     * de trainer en niet het kind zelf.
     */
    public function unenroll(User $user, Training $training): bool
    {
        return $user->belongsToSameSchool($training) && $user->isOuder();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    /**
     * Bewerken mag de eigenaar altijd; een trainer alleen bij zijn eigen werk.
     * Anders koppelt hij zich via een andere training aan een groep die niet
     * van hem is, en ziet hij daarna die spelers (zie TrainerScope).
     */
    public function update(User $user, Training $training): bool
    {
        return $this->recordAttendance($user, $training);
    }

    public function delete(User $user, Training $training): bool
    {
        return $user->belongsToSameSchool($training) && $user->isEigenaar();
    }

    /**
     * Aanwezigheid afvinken is werk van de trainer - bij zijn eigen werk.
     *
     * Het rooster ziet hij schoolbreed (invallen moet kunnen zien wat er
     * staat), maar afvinken en beoordelen alleen waar hij bij hoort: een
     * training waar hij aan gekoppeld is, of van een van zijn groepen. Is hij
     * nérgens gekoppeld, dan is de hele school van hem (zie TrainerScope).
     */
    public function recordAttendance(User $user, Training $training): bool
    {
        if (! $user->belongsToSameSchool($training)) {
            return false;
        }

        if ($user->isEigenaar()) {
            return true;
        }

        if (! $user->isTrainer()) {
            return false;
        }

        $scope = app(TrainerScope::class);
        $groepen = $scope->groupIds($user);

        if ($groepen === null) {
            return true;
        }

        if ($training->trainers()->whereKey($user->id)->exists()) {
            return true;
        }

        if ($training->group_id !== null) {
            return in_array($training->group_id, $groepen, true);
        }

        // Een privétraining heeft geen groep: dan gaat het om het kind.
        $speler = $training->slot?->player;

        return $speler !== null && $scope->ownsPlayer($user, $speler);
    }
}
