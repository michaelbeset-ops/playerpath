<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Tarieven en abonnementsvormen zijn van de eigenaar. Een trainer heeft hier
 * niets te zoeken: hij beoordeelt spelers, hij bepaalt geen prijzen.
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->belongsToSameSchool($plan) && $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->belongsToSameSchool($plan) && $user->isEigenaar();
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->belongsToSameSchool($plan) && $user->isEigenaar();
    }
}
