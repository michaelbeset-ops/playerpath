<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaitlistInvitation;

/** Van de eigenaar: dit is de administratie van de school. */
class WaitlistInvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, WaitlistInvitation $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, WaitlistInvitation $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }

    public function delete(User $user, WaitlistInvitation $model): bool
    {
        return $user->belongsToSameSchool($model) && $user->isEigenaar();
    }
}
