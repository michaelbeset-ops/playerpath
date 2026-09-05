<?php

namespace App\Support\Trainings;

use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Welke trainingen ziet deze gebruiker?
 *
 * Eigenaar en trainer zien de hele school. Een ouder of speler ziet alleen
 * trainingen van groepen waar hun eigen speler in zit. Dat staat hier op één
 * plek, zodat het overzicht, de detailpagina en de aanmeldknop niet uit elkaar
 * kunnen lopen.
 *
 * Dit staat náást de global scope, niet in plaats daarvan: de school-grens
 * wordt al door SchoolScope bewaakt.
 */
class VisibleTrainings
{
    public function query(User $user): Builder
    {
        $query = Training::query()->with('group');

        if ($user->isEigenaar() || $user->isTrainer()) {
            return $query;
        }

        $playerIds = $user->visiblePlayerIds();

        if ($playerIds === []) {
            // Geen eigen spelers, dus ook geen trainingen. Fail-closed.
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'group.players',
            fn (Builder $players) => $players->whereIn('players.id', $playerIds)
        );
    }
}
