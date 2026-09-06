<?php

namespace App\Support\Navigation;

use App\Enums\Feature;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Player;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\Training;
use App\Models\User;
use App\Support\Features\Features;

/**
 * Het hoofdmenu, bepaald door wat je mag.
 *
 * Bewust server-side en op één plek: een menu-item dat je toch niet mag openen
 * is een dode klik. De policies zijn hier de enige bron — zo kan het menu niet
 * uit de pas lopen met de autorisatie.
 *
 * Het icoon gaat als naam mee; de Vue-kant zet dat om naar een component.
 */
class MainNavigation
{
    public function __construct(protected Features $features) {}

    /** @return list<array{section: string, title: string, href: string, icon: string}> */
    public function for(?User $user): array
    {
        if ($user === null || $user->school_id === null) {
            return [];
        }

        // Drie secties: het dagelijkse werk, geld en overzichten. Een lange
        // platte lijst wordt onoverzichtelijk zodra er meer dan tien items zijn.
        $items = [
            ['section' => 'School', 'title' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard', 'allowed' => true],
            ['section' => 'School', 'title' => 'Gebruikers', 'href' => '/users', 'icon' => 'players', 'allowed' => $user->can('viewAny', Player::class)],
            ['section' => 'School', 'title' => 'Groepen', 'href' => '/groups', 'icon' => 'groups', 'allowed' => $user->can('viewAny', Group::class)],
            ['section' => 'School', 'title' => 'Trainingen', 'href' => '/trainings', 'icon' => 'trainings', 'allowed' => $user->can('viewAny', Training::class)],
            ['section' => 'School', 'title' => 'Kalender', 'href' => '/calendar', 'icon' => 'calendar', 'allowed' => $user->can('viewAny', Training::class), 'feature' => Feature::Kalender],
            ['section' => 'School', 'title' => 'Rapporten', 'href' => '/reports', 'icon' => 'reports', 'allowed' => $user->can('viewAny', Report::class), 'feature' => Feature::Ontwikkeling],
            ['section' => 'School', 'title' => 'Mededelingen', 'href' => '/announcements', 'icon' => 'announcements', 'allowed' => $user->can('viewAny', Announcement::class), 'feature' => Feature::Mededelingen],
            ['section' => 'Financieel', 'title' => 'Inschrijvingen', 'href' => '/enrollments', 'icon' => 'enrollments', 'allowed' => $user->can('viewAny', Enrollment::class), 'feature' => Feature::Inschrijvingen],
            ['section' => 'Financieel', 'title' => 'Abonnementen', 'href' => '/subscriptions', 'icon' => 'subscriptions', 'allowed' => $user->can('viewAny', Subscription::class), 'feature' => Feature::Betalingen],
            ['section' => 'Financieel', 'title' => 'Betalingen', 'href' => '/payments', 'icon' => 'payments', 'allowed' => $user->can('viewAny', Payment::class), 'feature' => Feature::Betalingen],
            ['section' => 'Financieel', 'title' => 'Tarieven', 'href' => '/plans', 'icon' => 'plans', 'allowed' => $user->can('viewAny', Plan::class), 'feature' => Feature::Betalingen],
            ['section' => 'Financieel', 'title' => 'Overzichten', 'href' => '/exports', 'icon' => 'exports', 'allowed' => $user->isEigenaar(), 'feature' => Feature::Exports],
            ['section' => 'School', 'title' => 'Verantwoording', 'href' => '/verantwoording', 'icon' => 'accountability', 'allowed' => $user->isEigenaar()],
            ['section' => 'School', 'title' => 'Huisstijl', 'href' => '/branding', 'icon' => 'branding', 'allowed' => $user->isEigenaar()],
            // Ouder en speler: hun eigen abonnement, niet dat van de school.
            ['section' => 'School', 'title' => 'Mijn abonnement', 'href' => '/billing', 'icon' => 'payments', 'allowed' => $user->visiblePlayerIds() !== [], 'feature' => Feature::Betalingen],
        ];

        return array_values(array_map(
            fn (array $item) => ['section' => $item['section'], 'title' => $item['title'], 'href' => $item['href'], 'icon' => $item['icon']],
            // Twee voorwaarden: mag deze rol het, en heeft deze school het.
            // Het menu is cosmetica — de routes zitten zelf dicht (zie
            // RequireFeature) — maar een item dat naar een 404 wijst is een
            // dode klik, en dat is precies waarom dit menu server-side is.
            array_filter(
                $items,
                fn (array $item) => $item['allowed']
                    && (! isset($item['feature']) || $this->features->enabled($item['feature'])),
            ),
        ));
    }
}
