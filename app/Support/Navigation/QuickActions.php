<?php

namespace App\Support\Navigation;

use App\Enums\Feature;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use App\Models\User;
use App\Support\Features\Features;

/**
 * De plusknop in de balk: alles wat je vanaf hier kunt aanmaken.
 *
 * Zelfde afspraak als bij het hoofdmenu: de policies bepalen wat er staat, dus
 * er kan geen knop verschijnen die op een 403 uitloopt. Zijn er geen acties,
 * dan is er ook geen knop — een plus die een leeg lijstje opent is erger dan
 * geen plus.
 *
 * De volgorde is die van hoe vaak je het doet, niet die van het menu. Een
 * rapport invullen is de kern van het product en staat daarom bovenaan.
 */
class QuickActions
{
    public function __construct(protected Features $features) {}

    /** @return list<array{title: string, href: string, icon: string}> */
    public function for(?User $user): array
    {
        if ($user === null || $user->school_id === null) {
            return [];
        }

        $acties = [
            ['title' => 'Rapport invullen', 'href' => '/reports', 'icon' => 'reports', 'allowed' => $user->can('viewAny', Report::class), 'feature' => Feature::Ontwikkeling],
            ['title' => 'Training inplannen', 'href' => '/trainings/create', 'icon' => 'trainings', 'allowed' => $user->can('create', Training::class)],
            ['title' => 'Speler toevoegen', 'href' => '/players/create', 'icon' => 'players', 'allowed' => $user->can('create', Player::class)],
            ['title' => 'Groep toevoegen', 'href' => '/groups/create', 'icon' => 'groups', 'allowed' => $user->can('create', Group::class)],
            // Het berichtenoverzicht is schoolbreed en dus van de eigenaar; een
            // trainer stuurt een afgelasting vanaf de training zelf.
            ['title' => 'Mededeling sturen', 'href' => '/announcements', 'icon' => 'announcements', 'allowed' => $user->can('viewAny', Announcement::class), 'feature' => Feature::Mededelingen],
            ['title' => 'Betaling vastleggen', 'href' => '/payments', 'icon' => 'payments', 'allowed' => $user->can('viewAny', Payment::class), 'feature' => Feature::Betalingen],
        ];

        return array_values(array_map(
            fn (array $actie) => ['title' => $actie['title'], 'href' => $actie['href'], 'icon' => $actie['icon']],
            array_filter(
                $acties,
                fn (array $actie) => $actie['allowed']
                    && (! isset($actie['feature']) || $this->features->enabled($actie['feature'])),
            ),
        ));
    }
}
