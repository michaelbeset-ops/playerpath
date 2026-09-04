<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    public function view(User $user, Group $group): bool
    {
        return $user->belongsToSameSchool($group)
            && ($user->isEigenaar() || $user->isTrainer());
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, Group $group): bool
    {
        return $user->belongsToSameSchool($group) && $user->isEigenaar();
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->belongsToSameSchool($group) && $user->isEigenaar();
    }
}
