<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

/**
 * Locaties zijn van het bedrijf, niet van een klant.
 *
 * De eigenaar beheert ze. Een trainer kiest er een bij het inplannen van een
 * training, maar dat gaat via het formulier en niet via dit overzicht: het
 * beheerscherm hoort bij Mijn bedrijf, en dat is niet van de trainer.
 */
class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
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
