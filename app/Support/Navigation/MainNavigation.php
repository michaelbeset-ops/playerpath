<?php

namespace App\Support\Navigation;

use App\Enums\Feature;
use App\Models\Announcement;
use App\Models\AvailabilityException;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
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
 *
 * ## Groepen in plaats van een platte lijst
 *
 * Het menu staat bovenin, in een balk, en daar passen geen vijftien items
 * naast elkaar. Vandaar groepen: een handvol woorden in de balk, met daaronder
 * een uitklap. De indeling volgt hoe iemand naar zijn school kijkt — de agenda,
 * de klanten, het geld — en niet hoe de code is ingedeeld.
 *
 * Twee regels die het menu eerlijk houden:
 *
 * 1. **Een groep zonder zichtbare items verdwijnt.** Een uitklap die leeg is
 *    laat je zoeken naar iets wat er niet is.
 * 2. **Een groep met één zichtbaar item wordt dat item.** Heet de groep dan
 *    nog "Financiën" terwijl er alleen Betalingen in zit, dan klik je op een
 *    woord dat iets anders belooft dan het doet.
 */
class MainNavigation
{
    public function __construct(protected Features $features) {}

    /**
     * @return list<array{title: string, href: string|null, icon: string, items: list<array{title: string, href: string, icon: string}>}>
     */
    public function for(?User $user): array
    {
        if ($user === null || $user->school_id === null) {
            return [];
        }

        return $this->build($this->groups($user));
    }

    /**
     * Alles wat er kán staan, met per item wie het mag zien.
     *
     * @return list<array<string, mixed>>
     */
    protected function groups(User $user): array
    {
        // Een trainer is personeel en ziet zijn eigen werk. Zijn menu is klein:
        // trainingen, spelers, rapporten, verjaardagen — en zijn eigen account.
        // Geen Klanten, Financiën of Mijn bedrijf: dat is het bedrijf, en de
        // routes erachter weigeren hem sowieso (zie de policies).
        if ($user->isTrainer() && ! $user->isEigenaar()) {
            return $this->trainerGroups($user);
        }

        return [
            [
                'title' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/dashboard', 'allowed' => true, 'items' => [],
            ],
            [
                'title' => 'Agenda', 'icon' => 'trainings', 'items' => [
                    ['title' => 'Kalender', 'href' => '/calendar', 'icon' => 'calendar', 'allowed' => $user->can('viewAny', Training::class), 'feature' => Feature::Kalender],
                    ['title' => 'Trainingen', 'href' => '/trainings', 'icon' => 'trainings', 'allowed' => $user->can('viewAny', Training::class)],
                    // Alleen voor wie zelf voor de groep staat; een ouder heeft
                    // geen "mijn" trainingen.
                    ['title' => 'Mijn trainingen', 'href' => '/trainings/mijn', 'icon' => 'trainings', 'allowed' => $user->isTrainer() || $user->isEigenaar()],
                ],
            ],
            [
                // "Klanten", niet "Gebruikers": een school denkt in de mensen
                // die bij haar sporten, niet in accounts.
                'title' => 'Klanten', 'icon' => 'players', 'items' => [
                    // Eén item, geen Spelers en Ouders naast elkaar: de ouders
                    // staan uitklapbaar bij hun kind.
                    ['title' => 'Klanten', 'href' => '/clients', 'icon' => 'players', 'allowed' => $user->can('viewAny', Player::class)],
                    ['title' => 'Groepen', 'href' => '/groups', 'icon' => 'groups', 'allowed' => $user->can('viewAny', Group::class)],
                    ['title' => 'Inschrijvingen', 'href' => '/enrollments', 'icon' => 'enrollments', 'allowed' => $user->can('viewAny', Enrollment::class), 'feature' => Feature::Inschrijvingen],
                ],
            ],
            [
                'title' => 'Ontwikkeling', 'icon' => 'reports', 'items' => [
                    ['title' => 'Rapporten', 'href' => '/reports', 'icon' => 'reports', 'allowed' => $user->can('viewAny', Report::class), 'feature' => Feature::Ontwikkeling],
                    ['title' => 'Mijlpalen', 'href' => '/mijlpalen', 'icon' => 'badges', 'allowed' => $user->isEigenaar(), 'feature' => Feature::Ontwikkeling],
                ],
            ],
            [
                'title' => 'Financiën', 'icon' => 'payments', 'items' => [
                    ['title' => 'Betalingen', 'href' => '/payments', 'icon' => 'payments', 'allowed' => $user->can('viewAny', Payment::class), 'feature' => Feature::Betalingen],
                    ['title' => 'Abonnementen', 'href' => '/subscriptions', 'icon' => 'subscriptions', 'allowed' => $user->can('viewAny', Subscription::class), 'feature' => Feature::Betalingen],
                    ['title' => 'Aanbod', 'href' => '/aanbod', 'icon' => 'products', 'allowed' => $user->can('viewAny', Product::class), 'feature' => Feature::Betalingen],
                    ['title' => 'Overzichten', 'href' => '/exports', 'icon' => 'exports', 'allowed' => $user->isEigenaar(), 'feature' => Feature::Exports],
                    // Alleen de ouder: die betaalt. Een kind met een eigen inlog
                    // ziet zijn kaart en zijn voortgang, geen rekeningen of shop.
                    ['title' => 'Betalingen', 'href' => '/billing', 'icon' => 'payments', 'allowed' => $user->isOuder(), 'feature' => Feature::Betalingen, 'badge' => fn () => $this->openstaand($user)],
                    // En wat ze er zelf bij kunnen afnemen: rittenkaarten, kampen.
                    ['title' => 'Shop', 'href' => '/shop', 'icon' => 'products', 'allowed' => $user->isOuder(), 'feature' => Feature::Betalingen],
                ],
            ],
            [
                'title' => 'Mededelingen', 'icon' => 'announcements', 'items' => [
                    ['title' => 'Berichten', 'href' => '/announcements', 'icon' => 'announcements', 'allowed' => $user->can('viewAny', Announcement::class), 'feature' => Feature::Mededelingen],
                    ['title' => 'Verjaardagsmail', 'href' => '/announcements/verjaardagen', 'icon' => 'birthdays', 'allowed' => $user->isEigenaar(), 'feature' => Feature::Mededelingen],
                ],
            ],
            [
                // Alles wat over de school zelf gaat en niet over een klant.
                'title' => 'Mijn bedrijf', 'icon' => 'business', 'items' => [
                    ['title' => 'Personeel', 'href' => '/staff', 'icon' => 'staff', 'allowed' => $user->isEigenaar()],
                    // Je eigen beschikbaarheid vul je in; het overzicht van het
                    // hele team is van de eigenaar, die er zijn planning op maakt.
                    ['title' => 'Mijn beschikbaarheid', 'href' => '/beschikbaarheid', 'icon' => 'availability', 'allowed' => $user->can('viewAny', AvailabilityException::class)],
                    ['title' => 'Beschikbaarheid team', 'href' => '/personeel/beschikbaarheid', 'icon' => 'availability', 'allowed' => $user->can('viewTeam', AvailabilityException::class)],
                    ['title' => 'Locaties', 'href' => '/locaties', 'icon' => 'locations', 'allowed' => $user->can('viewAny', Location::class)],
                    ['title' => 'Inschrijven en betalen', 'href' => '/instellingen/inschrijven', 'icon' => 'enrollments', 'allowed' => $user->isEigenaar()],
                    ['title' => 'Huisstijl', 'href' => '/branding', 'icon' => 'branding', 'allowed' => $user->isEigenaar()],
                    ['title' => 'Verantwoording', 'href' => '/verantwoording', 'icon' => 'accountability', 'allowed' => $user->isEigenaar()],
                    ['title' => 'Instellingen', 'href' => '/settings/profile', 'icon' => 'settings', 'allowed' => true],
                ],
            ],
        ];
    }

    /**
     * Filteren en inklappen.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    protected function build(array $groups): array
    {
        $zichtbaar = [];

        foreach ($groups as $groep) {
            // Een groep zonder eigen items is zelf een link (Dashboard).
            if ($groep['items'] === []) {
                if ($this->mag($groep)) {
                    $zichtbaar[] = ['title' => $groep['title'], 'href' => $groep['href'], 'icon' => $groep['icon'], 'items' => []];
                }

                continue;
            }

            $items = array_values(array_map(
                fn (array $item) => ['title' => $item['title'], 'href' => $item['href'], 'icon' => $item['icon'], 'badge' => isset($item['badge']) ? (int) $item['badge']() : 0],
                array_filter($groep['items'], fn (array $item) => $this->mag($item)),
            ));

            if ($items === []) {
                continue;
            }

            // Eén item over: dan is de groepsnaam een belofte die hij niet
            // waarmaakt. Toon het item zelf.
            if (count($items) === 1) {
                $zichtbaar[] = ['title' => $items[0]['title'], 'href' => $items[0]['href'], 'icon' => $items[0]['icon'], 'items' => [], 'badge' => $items[0]['badge']];

                continue;
            }

            $zichtbaar[] = ['title' => $groep['title'], 'href' => null, 'icon' => $groep['icon'], 'items' => $items, 'badge' => (int) array_sum(array_column($items, 'badge'))];
        }

        return $zichtbaar;
    }

    /**
     * Twee voorwaarden: mag deze rol het, en heeft deze school het.
     *
     * Het menu is cosmetica — de routes zitten zelf dicht (zie RequireFeature)
     * — maar een item dat naar een 404 wijst is een dode klik, en dat is
     * precies waarom dit menu server-side is.
     *
     * @param  array<string, mixed>  $item
     */
    /**
     * Hoeveel rekeningen er voor de kinderen van deze ouder openstaan.
     *
     * Het meldingsblok op het dashboard is weg; dit bolletje is wat er voor in
     * de plaats kwam. Subtiel, maar niet te missen.
     */
    protected function openstaand(User $user): int
    {
        $spelers = $user->visiblePlayerIds();

        return $spelers === [] ? 0 : Payment::whereIn('player_id', $spelers)->outstanding()->count();
    }

    protected function mag(array $item): bool
    {
        return ($item['allowed'] ?? false)
            && (! isset($item['feature']) || $this->features->enabled($item['feature']));
    }

    /**
     * Het menu van een trainer.
     *
     * Vijf dingen. "Spelers" wijst naar het klantenoverzicht, dat voor hem al
     * tot zijn eigen spelers is begrensd (TrainerScope); de naam is anders
     * omdat een trainer geen klanten heeft, hij heeft spelers.
     *
     * @return list<array<string, mixed>>
     */
    protected function trainerGroups(User $user): array
    {
        return [
            [
                'title' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/dashboard', 'allowed' => true, 'items' => [],
            ],
            [
                'title' => 'Trainingen', 'icon' => 'trainings', 'items' => [
                    ['title' => 'Mijn trainingen', 'href' => '/trainings/mijn', 'icon' => 'trainings', 'allowed' => true],
                    ['title' => 'Kalender', 'href' => '/calendar', 'icon' => 'calendar', 'allowed' => $user->can('viewAny', Training::class), 'feature' => Feature::Kalender],
                    ['title' => 'Rooster', 'href' => '/trainings', 'icon' => 'trainings', 'allowed' => $user->can('viewAny', Training::class)],
                ],
            ],
            [
                'title' => 'Spelers', 'icon' => 'players', 'href' => '/clients', 'allowed' => $user->can('viewAny', Player::class), 'items' => [],
            ],
            [
                'title' => 'Rapporten', 'icon' => 'reports', 'href' => '/reports', 'allowed' => $user->can('viewAny', Report::class), 'feature' => Feature::Ontwikkeling, 'items' => [],
            ],
            [
                'title' => 'Verjaardagen', 'icon' => 'birthdays', 'href' => '/verjaardagen', 'allowed' => true, 'items' => [],
            ],
            [
                // Wat over hemzelf gaat, niet over de school.
                'title' => 'Mijn account', 'icon' => 'settings', 'items' => [
                    ['title' => 'Mijn beschikbaarheid', 'href' => '/beschikbaarheid', 'icon' => 'availability', 'allowed' => $user->can('viewAny', AvailabilityException::class)],
                    ['title' => 'Instellingen', 'href' => '/settings/profile', 'icon' => 'settings', 'allowed' => true],
                ],
            ],
        ];
    }
}
