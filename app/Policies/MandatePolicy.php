<?php

namespace App\Policies;

use App\Models\Mandate;
use App\Models\User;

/**
 * Een mandaat is van de ouder die het gaf; de eigenaar ziet ze voor de administratie.
 */
class MandatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isOuder();
    }

    public function view(User $user, Mandate $model): bool
    {
        if (! $user->belongsToSameSchool($model)) {
            return false;
        }

        return $user->isEigenaar() || $model->user_id === $user->id;
    }

    public function update(User $user, Mandate $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }
}
