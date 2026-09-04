<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    /** Je eigen account verwijderen mag; dat van een ander alleen als eigenaar. */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }
}
