<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Support\Trainers\TrainerScope;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    public function view(User $user, Group $group): bool
    {
        if (! $user->belongsToSameSchool($group)) {
            return false;
        }

        // Een trainer ziet de groepen waar hij voor staat; zie TrainerScope.
        return $user->isEigenaar()
            || ($user->isTrainer() && app(TrainerScope::class)->ownsGroup($user, $group));
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
