<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Rapporten schrijft de trainer (en de eigenaar). Lezen mag iedereen die de
 * speler zelf ook mag zien - dat regelt PlayerPolicy::view.
 */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    public function view(User $user, Report $report): bool
    {
        return $user->belongsToSameSchool($report)
            && $user->can('view', $report->player);
    }

    public function create(User $user): bool
    {
        return $user->isTrainer() || $user->isEigenaar();
    }

    public function update(User $user, Report $report): bool
    {
        if (! $user->belongsToSameSchool($report)) {
            return false;
        }

        // Een trainer past alleen zijn eigen rapport aan; de eigenaar alles.
        return $user->isEigenaar() || ($user->isTrainer() && $report->trainer_id === $user->id);
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->belongsToSameSchool($report) && $user->isEigenaar();
    }
}
