<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

/**
 * Inschrijvingen beoordelen is werk van de eigenaar: hij beslist wie er in
 * het ledenbestand komt en wat dat kost.
 */
class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->belongsToSameSchool($enrollment) && $user->isEigenaar();
    }

    /** Annuleren: de school, of de ouder van dit kind. */
    public function cancel(User $user, Enrollment $enrollment): bool
    {
        if (! $user->belongsToSameSchool($enrollment)) {
            return false;
        }

        if ($user->isEigenaar()) {
            return true;
        }

        return $user->isOuder() && (
            $enrollment->guardian_user_id === $user->id
            || ($enrollment->player_id !== null && in_array($enrollment->player_id, $user->visiblePlayerIds(), true))
        );
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->belongsToSameSchool($enrollment) && $user->isEigenaar();
    }
}
