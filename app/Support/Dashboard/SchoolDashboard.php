<?php

namespace App\Support\Dashboard;

use App\Enums\AttendanceStatus;
use App\Enums\PlayerPosition;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Goal;
use App\Models\Group;
use App\Models\Player;
use App\Models\Report;
use App\Models\Training;

/**
 * De cijfers voor het eigenaar-dashboard.
 *
 * Alles hier is een telling over de eigen school; de global scope regelt die
 * grens, dus er staat nergens een handmatige filter op school_id.
 *
 * Alleen cijfers die nu echt bestaan. Liever een leeg vak met uitleg dan een
 * getal dat nergens op slaat.
 */
class SchoolDashboard
{
    /** Na hoeveel dagen zonder rapport een speler aandacht verdient. */
    public const AANDACHT_NA_DAGEN = 30;

    /** @return array<string, mixed> */
    public function stats(): array
    {
        $actief = Player::active();

        return [
            'players' => (clone $actief)->count(),
            'keepers' => (clone $actief)->where('position', PlayerPosition::Keeper->value)->count(),
            'averageRating' => $this->gemiddeldeRating(),
            'reportsThisWeek' => Report::where('reported_on', '>=', now()->startOfWeek())->count(),
            'trainingsThisWeek' => Training::whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'attendanceRate' => $this->opkomst(),
            'groups' => Group::where('is_active', true)->count(),
            'pendingEnrollments' => Enrollment::pending()->count(),
            'playersWithGoal' => Goal::active()->distinct('player_id')->count('player_id'),
        ];
    }

    /** Het gemiddelde van alle spelerskaarten met een cijfer. */
    protected function gemiddeldeRating(): ?int
    {
        $gemiddelde = Player::active()->whereNotNull('overall_rating')->avg('overall_rating');

        return $gemiddelde === null ? null : (int) round($gemiddelde);
    }

    /**
     * Opkomst over de laatste 30 dagen.
     *
     * Alleen op basis van wat de trainer echt heeft afgevinkt: spelers zonder
     * vinkje tellen niet mee, want "niet afgevinkt" is geen "afwezig".
     *
     * @return array{percentage: int|null, present: int, total: int}
     */
    protected function opkomst(): array
    {
        $afgevinkt = Attendance::whereNotNull('status')
            ->whereHas('training', fn ($q) => $q->where('starts_at', '>=', now()->subDays(30)))
            ->get(['status']);

        $totaal = $afgevinkt->count();
        $aanwezig = $afgevinkt->where('status', AttendanceStatus::Present)->count();

        return [
            'percentage' => $totaal === 0 ? null : (int) round($aanwezig / $totaal * 100),
            'present' => $aanwezig,
            'total' => $totaal,
        ];
    }

    /**
     * Spelers die te lang geen rapport hebben gehad.
     *
     * Dit is het belangrijkste lijstje voor een eigenaar: het laat zien waar
     * zijn product stilvalt. Een speler zonder rapporten heeft een lege kaart,
     * en een lege kaart is precies waarom een ouder afhaakt.
     *
     * @return list<array<string, mixed>>
     */
    public function needsAttention(int $limit = 8): array
    {
        $grens = now()->subDays(self::AANDACHT_NA_DAGEN);

        return Player::active()
            ->withMax('reports', 'reported_on')
            ->get()
            ->filter(fn (Player $speler) => $speler->reports_max_reported_on === null
                || $speler->reports_max_reported_on < $grens->toDateString())
            ->sortBy(fn (Player $speler) => $speler->reports_max_reported_on ?? '')
            ->take($limit)
            ->map(fn (Player $speler) => [
                'id' => $speler->id,
                'name' => $speler->full_name,
                'position' => $speler->position->label(),
                'overall_rating' => $speler->overall_rating,
                'last_report_on' => $speler->reports_max_reported_on
                    ? \Illuminate\Support\Carbon::parse($speler->reports_max_reported_on)->format('d-m-Y')
                    : null,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function upcomingTrainings(int $limit = 5): array
    {
        return Training::upcoming()
            ->with('group')
            ->limit($limit)
            ->get()
            ->map(fn (Training $training) => [
                'id' => $training->id,
                'group' => $training->group->name,
                'date' => $training->starts_at->translatedFormat('l j F'),
                'time' => $training->starts_at->format('H:i'),
                'location' => $training->location,
            ])
            ->all();
    }
}
