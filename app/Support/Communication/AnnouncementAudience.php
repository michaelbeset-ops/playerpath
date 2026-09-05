<?php

namespace App\Support\Communication;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Wie krijgt een mededeling te zien.
 *
 * Drie afspraken:
 *
 * 1. **Ouders én spelers met een eigen inlog.** Een keeper van zestien leest
 *    zijn eigen berichten; een keeper van acht heeft alleen een ouder.
 * 2. **Alleen actieve spelers.** Wie gestopt is hoort niet meer bij de school
 *    en krijgt dus ook geen afgelasting meer binnen.
 * 3. **Nooit dubbel.** Een ouder met twee kinderen in dezelfde groep krijgt
 *    één bericht, niet twee.
 */
class AnnouncementAudience
{
    /** @return Collection<int, User> */
    public function for(Announcement $announcement): Collection
    {
        return $this->forGroup($announcement->group);
    }

    /**
     * @param  Group|null  $group  null betekent de hele school
     * @return Collection<int, User>
     */
    public function forGroup(?Group $group): Collection
    {
        $spelers = Player::query()
            ->where('is_active', true)
            ->when($group !== null, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group->id)))
            ->with(['guardians', 'user'])
            ->get();

        return $spelers
            ->flatMap(function (Player $speler) {
                // Bewust geen array-plus: die voegt samen op sleutel en zou bij
                // twee lijsten met dezelfde nummering ontvangers weggooien.
                $ontvangers = $speler->guardians->all();

                if ($speler->user !== null) {
                    $ontvangers[] = $speler->user;
                }

                return $ontvangers;
            })
            ->unique('id')
            ->values();
    }

    /** Hoeveel gezinnen bereik je hiermee, zonder al te versturen. */
    public function countForGroup(?Group $group): int
    {
        return $this->forGroup($group)->count();
    }
}
