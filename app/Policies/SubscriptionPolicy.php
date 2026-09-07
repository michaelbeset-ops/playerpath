<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        if (! $user->belongsToSameSchool($subscription)) {
            return false;
        }

        if ($user->isEigenaar()) {
            return true;
        }

        return in_array($subscription->player_id, $user->visiblePlayerIds(), strict: true);
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    /** Opzeggen: de school, of de ouder van dit kind. */
    public function cancel(User $user, Subscription $subscription): bool
    {
        if (! $user->belongsToSameSchool($subscription)) {
            return false;
        }

        return $user->isEigenaar()
            || ($user->isOuder() && in_array($subscription->player_id, $user->visiblePlayerIds(), true));
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->belongsToSameSchool($subscription) && $user->isEigenaar();
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->belongsToSameSchool($subscription) && $user->isEigenaar();
    }
}
