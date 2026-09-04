<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function view(User $user, School $school): bool
    {
        return $user->school_id === $school->id;
    }

    /** Alleen de eigenaar past zijn eigen school aan. */
    public function update(User $user, School $school): bool
    {
        return $user->school_id === $school->id && $user->isEigenaar();
    }

    /** Scholen aanmaken en verwijderen is platformbeheer, geen app-actie. */
    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, School $school): bool
    {
        return false;
    }
}
