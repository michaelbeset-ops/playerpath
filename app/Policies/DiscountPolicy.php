<?php

namespace App\Policies;

use App\Models\Discount;
use App\Models\User;

/** Van de eigenaar: dit is de administratie van de school. */
class DiscountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, Discount $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, Discount $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    public function delete(User $user, Discount $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }
}
