<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

/**
 * Locaties zijn van het bedrijf, niet van een klant.
 *
 * De eigenaar beheert ze; een trainer mag ze zien, want hij kiest er een bij
 * het inplannen van een training.
 */
class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, Location $location): bool
    {
        return $user->belongsToSameSchool($location) && $user->isEigenaar();
    }
}
