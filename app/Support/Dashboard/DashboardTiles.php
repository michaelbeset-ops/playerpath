<?php

namespace App\Support\Dashboard;

use App\Enums\DashboardTile;
use App\Models\User;
use App\Support\Payments\BillingOverview;

/**
 * De gekozen tegels omzetten naar wat er op het scherm komt.
 *
 * Waarde, toelichting, icoon en toon worden hier bepaald en niet in Vue. Dat
 * houdt het scherm dom en zet de Nederlandse teksten op de plek waar ze horen,
 * naast de cijfers waar ze bij horen.
 *
 * **Er wordt alleen gerekend wat er ook getoond wordt.** De financiële cijfers
 * kosten een paar queries; die draaien niet voor een school die het vak heeft
 * uitgezet of voor een trainer die er niet bij mag.
 */
class DashboardTiles
{
    public function __construct(
        protected SchoolDashboard $dashboard,
        protected DashboardPreferences $preferences,
        protected BillingOverview $billing,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function for(User $user): array
    {
        $tegels = $this->preferences->tiles($user);

        if ($tegels === []) {
            return [];
        }

        $stats = $this->dashboard->stats();

        // Alleen ophalen als er ook echt een geldtegel bij zit.
        $geld = collect($tegels)->contains(fn (DashboardTile $t) => $t->ownerOnly())
            ? $this->billing->summary()
            : null;

        return array_values(array_map(
            fn (DashboardTile $tegel) => $this->render($tegel, $stats, $geld),
            $tegels,
        ));
    }

    /**
     * @param  array<string, mixed>  $s
     * @param  array<string, mixed>|null  $geld
     * @return array<string, mixed>
     */
    protected function render(DashboardTile $tegel, array $s, ?array $geld): array
    {
        // Toon: groen leest als "goed". Een cijfer dat om actie vraagt krijgt
        // daarom een andere toon, anders zegt de kleur het tegendeel van wat
        // er staat. Zie CLAUDE.md, hoofdstuk 4.
        return [
            'key' => $tegel->value,
            'label' => $tegel->label(),
            ...match ($tegel) {
                DashboardTile::Players => [
                    'value' => $s['players'],
                    'hint' => $s['groups'].' '.($s['groups'] === 1 ? 'groep' : 'groepen'),
                    'icon' => 'players',
                    'href' => '/clients',
                ],
                DashboardTile::Keepers => [
                    'value' => $s['keepers'],
                    'hint' => 'van de '.$s['players'].' actieve spelers',
                    'icon' => 'players',
                    'href' => '/clients',
                ],
                DashboardTile::Groups => [
                    'value' => $s['groups'],
                    'hint' => 'actief',
                    'icon' => 'groups',
                    'href' => '/groups',
                ],
                DashboardTile::Rating => [
                    'value' => $s['averageRating'],
                    'hint' => $s['averageRating'] === null ? 'Nog geen rapporten' : 'over alle spelers met een rapport',
                    'icon' => 'rating',
                ],
                DashboardTile::Reports => [
                    'value' => $s['reportsThisWeek'],
                    'hint' => $s['reportsThisWeek'] === 0 ? 'deze week nog niemand beoordeeld' : 'ingevuld door je trainers',
                    'icon' => 'reports',
                    'href' => '/reports',
                ],
                DashboardTile::Goals => [
                    'value' => $s['playersWithGoal'],
                    'hint' => 'met een lopend doel',
                    'icon' => 'goals',
                ],
                DashboardTile::Trainings => [
                    'value' => $s['trainingsThisWeek'],
                    'hint' => 'op het rooster',
                    'icon' => 'trainings',
                    'href' => '/trainings',
                ],
                DashboardTile::Attendance => [
                    'value' => $s['attendanceRate']['percentage'] === null ? null : $s['attendanceRate']['percentage'].'%',
                    'hint' => $s['attendanceRate']['percentage'] === null
                        ? 'Nog niets afgevinkt'
                        : $s['attendanceRate']['present'].' van '.$s['attendanceRate']['total'].' laatste 30 dagen',
                    'icon' => 'attendance',
                    'href' => '/trainings',
                ],
                DashboardTile::Birthdays => [
                    'value' => $s['birthdaysThisMonth'],
                    'hint' => $s['birthdaysThisMonth'] === 0 ? 'deze maand niemand' : 'vergeet ze niet',
                    'icon' => 'birthdays',
                ],
                DashboardTile::Enrollments => [
                    'value' => $s['pendingEnrollments'],
                    'hint' => $s['pendingEnrollments'] === 0 ? 'niets te beoordelen' : 'wachten op je beslissing',
                    'icon' => 'enrollments',
                    'href' => '/enrollments',
                    'tone' => $s['pendingEnrollments'] > 0 ? 'warning' : null,
                ],
                DashboardTile::Revenue => [
                    'value' => $geld['revenueThisMonth'] ?? null,
                    'hint' => 'binnengekomen deze maand',
                    'icon' => 'revenue',
                    'href' => '/payments',
                ],
                DashboardTile::Outstanding => [
                    'value' => $geld['outstanding'] ?? null,
                    'hint' => ($geld['outstandingCount'] ?? 0).' '.(($geld['outstandingCount'] ?? 0) === 1 ? 'rekening' : 'rekeningen'),
                    'icon' => 'revenue',
                    'href' => '/payments',
                    'tone' => ($geld['outstandingCount'] ?? 0) > 0 ? 'warning' : null,
                ],
                DashboardTile::Overdue => [
                    'value' => $geld['overdue'] ?? null,
                    'hint' => ($geld['overdueCount'] ?? 0).' '.(($geld['overdueCount'] ?? 0) === 1 ? 'rekening' : 'rekeningen').' te laat',
                    'icon' => 'revenue',
                    'href' => '/payments',
                    'tone' => ($geld['overdueCount'] ?? 0) > 0 ? 'danger' : null,
                ],
                DashboardTile::Subscriptions => [
                    'value' => $geld['activeSubscriptions'] ?? null,
                    'hint' => ($geld['yearlyValue'] ?? '—').' op jaarbasis',
                    'icon' => 'subscriptions',
                    'href' => '/subscriptions',
                ],
            },
        ];
    }
}
