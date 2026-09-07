<?php

namespace App\Policies;

use App\Models\Consent;
use App\Models\User;

/**
 * Een toestemming is van de ouder die hem gaf; de eigenaar ziet ze voor de administratie.
 */
class ConsentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isOuder();
    }

    public function view(User $user, Consent $model): bool
    {
        if (! $user->belongsToSameSchool($model)) {
            return false;
        }

        return $user->isEigenaar() || $model->user_id === $user->id;
    }

    public function update(User $user, Consent $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }
}
