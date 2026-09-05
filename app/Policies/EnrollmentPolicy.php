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

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->belongsToSameSchool($enrollment) && $user->isEigenaar();
    }
}
