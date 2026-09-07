<?php

namespace App\Policies;

use App\Models\PaymentOption;
use App\Models\User;

/** Van de eigenaar: dit is de administratie van de school. */
class PaymentOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, PaymentOption $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, PaymentOption $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    public function delete(User $user, PaymentOption $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }
}
