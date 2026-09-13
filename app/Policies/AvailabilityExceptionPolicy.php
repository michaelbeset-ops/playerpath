<?php

namespace App\Policies;

use App\Models\AvailabilityException;
use App\Models\User;

/**
 * Je beschikbaarheid is van jou.
 *
 * De eigenaar mag hem inzien - hij maakt er zijn planning op - maar niemand
 * vult hem voor een ander in. "Jij kan die zaterdag wél" is geen mededeling die
 * een systeem hoort te doen.
 */
class AvailabilityExceptionPolicy
{
    /** Wie een eigen beschikbaarheid heeft: de trainer en de eigenaar. */
    public function viewAny(User $user): bool
    {
        return $user->isTrainer() || $user->isEigenaar();
    }

    /** Het overzicht van álle trainers is van de eigenaar. */
    public function viewTeam(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, AvailabilityException $exception): bool
    {
        return $user->belongsToSameSchool($exception) && $exception->user_id === $user->id;
    }
}
