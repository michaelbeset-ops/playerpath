<?php

namespace App\Support\Navigation;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Plan;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\Training;
use App\Models\User;

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
            ['section' => 'School', 'title' => 'Kalender', 'href' => '/calendar', 'icon' => 'calendar', 'allowed' => $user->can('viewAny', Training::class)],
            ['section' => 'School', 'title' => 'Rapporten', 'href' => '/reports', 'icon' => 'reports', 'allowed' => $user->can('viewAny', Report::class)],
            ['section' => 'Financieel', 'title' => 'Inschrijvingen', 'href' => '/enrollments', 'icon' => 'enrollments', 'allowed' => $user->can('viewAny', Enrollment::class)],
            ['section' => 'Financieel', 'title' => 'Abonnementen', 'href' => '/subscriptions', 'icon' => 'subscriptions', 'allowed' => $user->can('viewAny', Subscription::class)],
            ['section' => 'Financieel', 'title' => 'Betalingen', 'href' => '/payments', 'icon' => 'payments', 'allowed' => $user->can('viewAny', Payment::class)],
            ['section' => 'Financieel', 'title' => 'Tarieven', 'href' => '/plans', 'icon' => 'plans', 'allowed' => $user->can('viewAny', Plan::class)],
            ['section' => 'Financieel', 'title' => 'Overzichten', 'href' => '/exports', 'icon' => 'exports', 'allowed' => $user->isEigenaar()],
            // Ouder en speler: hun eigen abonnement, niet dat van de school.
            ['section' => 'School', 'title' => 'Mijn abonnement', 'href' => '/billing', 'icon' => 'payments', 'allowed' => $user->visiblePlayerIds() !== []],
        ];

        return array_values(array_map(
            fn (array $item) => ['section' => $item['section'], 'title' => $item['title'], 'href' => $item['href'], 'icon' => $item['icon']],
            array_filter($items, fn (array $item) => $item['allowed']),
        ));
    }
}
