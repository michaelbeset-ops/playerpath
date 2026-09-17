<?php

namespace App\Policies;

use App\Models\SchoolSlugRedirect;
use App\Models\User;

/**
 * Oude inschrijfadressen.
 *
 * Ze ontstaan vanzelf als een school haar slug wijzigt en er is geen scherm om
 * ze te beheren. Zien mag alleen de eigenaar van de eigen school; aanmaken,
 * wijzigen en verwijderen gaat uitsluitend via School::booted().
 */
class SchoolSlugRedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, SchoolSlugRedirect $redirect): bool
    {
        return $user->belongsToSameSchool($redirect) && $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SchoolSlugRedirect $redirect): bool
    {
        return false;
    }

    public function delete(User $user, SchoolSlugRedirect $redirect): bool
    {
        return false;
    }
}
