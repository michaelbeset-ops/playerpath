<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Wie mag mededelingen sturen.
 *
 * Eigenaar en trainer allebei. Een afgelasting komt in de praktijk van de
 * trainer die om zeven uur 's ochtends naar het veld kijkt; die moet daar niet
 * eerst de eigenaar voor hoeven bellen. Ouders en spelers ontvangen alleen.
 *
 * Elke check begint met "zelfde school", net als bij de andere policies: twee
 * sloten op dezelfde deur.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->belongsToSameSchool($announcement) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Verwijderen kan niet: een verstuurd bericht is verstuurd. Het uit het
     * overzicht halen zou alleen de schijn wekken dat het nooit gebeurd is,
     * terwijl het al in honderd mailboxen ligt.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        return false;
    }
}
