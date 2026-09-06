<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Tarieven en abonnementsvormen zijn van de eigenaar. Een trainer heeft hier
 * niets te zoeken: hij beoordeelt spelers, hij bepaalt geen prijzen.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->belongsToSameSchool($product) && $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->belongsToSameSchool($product) && $user->isEigenaar();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->belongsToSameSchool($product) && $user->isEigenaar();
    }
}
