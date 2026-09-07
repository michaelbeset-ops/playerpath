<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Een order is van de eigenaar (administratie) en van de ouder die hem betaalt.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isOuder();
    }

    public function view(User $user, Order $model): bool
    {
        if (! $user->belongsToSameSchool($model)) {
            return false;
        }

        return $user->isEigenaar() || $model->user_id === $user->id;
    }

    public function update(User $user, Order $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }
}
