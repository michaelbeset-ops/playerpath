<?php

namespace App\Support\Dashboard;

use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Het dashboard van een trainer.
 *
 * Een trainer is personeel, geen directie. Hij komt hier met twee vragen: waar
 * moet ik zijn, en wie moet ik nog beoordelen. Omzet, openstaande rekeningen en
 * schoolbrede instellingen staan er dus niet — dat is niet alleen "niet nuttig",
 * het is niet van hem.
 *
 * Twee afspraken die je niet moet omdraaien:
 *
 * - **Wat "mijn spelers" zijn staat op één plek**: `TrainerScope`. Dezelfde
 *   grens als de policies en de lijsten, zodat het dashboard nooit een speler
 *   toont die de trainer ergens anders niet mag openen.
 */
class TrainerDashboard
{
    /** Wanneer een speler te lang zonder rapport zit. Dezelfde grens als overal. */
    public const RAPPORT_NA_DAGEN = 30;

    /**
     * De eerstvolgende trainingen van deze trainer.
     *
     * @return list<array<string, mixed>>
     */
    public function trainings(User $user, int $limiet = 3): array
    {
        return Training::query()
            ->with(['group', 'trainers'])
            ->where('starts_at', '>=', now()->startOfDay())
            ->forTrainer($user)
            ->orderBy('starts_at')
            ->limit($limiet)
            ->get()
            ->map(fn (Training $training) => [
                'id' => $training->id,
                'group' => $training->label(),
                'date' => $training->starts_at->translatedFormat('l j F'),
                'dayNumber' => $training->starts_at->format('j'),
                'month' => $training->starts_at->translatedFormat('M'),
                'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
                'location' => $training->location,
                'cancelled' => $training->isCancelled(),
                'isToday' => $training->starts_at->isToday(),
                // Voorbij: dan is afvinken en beoordelen aan de beurt, en niet
                // meer "waar moet ik zijn".
                'hasStarted' => $training->starts_at->isPast(),
                'expected' => $training->expectedPlayers()->count(),
                'trainers' => $training->trainers->pluck('name')->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * De spelers uit zijn groepen, met hoe lang geleden ze beoordeeld zijn.
     *
     * De kleur zegt waar het stilvalt: recent is groen, langer dan dertig dagen
     * oranje. Dezelfde grens als het aandacht-blok, zodat "te lang geleden" in
     * de hele app hetzelfde betekent.
     *
     * @return array<string, mixed>
     */
    public function players(User $user, int $limiet = 8): array
    {
        $spelers = $this->eigenSpelers($user);

        $rijen = $spelers
            ->map(function (Player $speler) {
                // Uit withMax(), niet uit een query per speler: bij een school
                // met tweehonderd kinderen scheelt dat tweehonderd queries op
                // het scherm dat een trainer als eerste opent.
                $laatste = $speler->reports_max_reported_on === null
                    ? null
                    : CarbonImmutable::parse($speler->reports_max_reported_on);

                $dagen = $laatste?->startOfDay()->diffInDays(now()->startOfDay());

                return [
                    'id' => $speler->id,
                    'name' => $speler->full_name,
                    'first_name' => $speler->first_name,
                    'photo' => $speler->photo_url,
                    'rating' => $speler->overall_rating,
                    'last_report_on' => $laatste?->format('d-m-Y'),
                    'days_since_report' => $dagen === null ? null : (int) $dagen,
                    // Nooit beoordeeld telt als aandacht: een lege kaart is
                    // precies waarom een ouder afhaakt.
                    'tone' => $dagen !== null && $dagen <= self::RAPPORT_NA_DAGEN ? 'good' : 'warning',
                ];
            })
            // Wie het langst niets kreeg bovenaan; nooit beoordeeld eerst.
            ->sortByDesc(fn (array $rij) => $rij['days_since_report'] ?? PHP_INT_MAX)
            ->values();

        return [
            'players' => $rijen->take($limiet)->all(),
            'total' => $rijen->count(),
            'stale' => $rijen->where('tone', 'warning')->count(),
            'staleAfterDays' => self::RAPPORT_NA_DAGEN,
        ];
    }

    /**
     * De actieve spelers uit de groepen waar deze trainer voor staat.
     *
     * @return Collection<int, Player>
     */
    protected function eigenSpelers(User $user): Collection
    {
        return Player::query()
            ->active()
            ->withMax('reports', 'reported_on')
            // Dezelfde grens als de policies en de lijsten: zie TrainerScope.
            ->visibleTo($user)
            ->orderBy('first_name')
            ->get();
    }
}
