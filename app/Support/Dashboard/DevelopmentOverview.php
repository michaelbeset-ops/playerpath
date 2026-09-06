<?php

namespace App\Support\Dashboard;

use App\Models\Player;
use App\Models\Report;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Wie er groeit en wie er achterblijft.
 *
 * Dit is het onderscheidende deel van het product, dus het krijgt een groot
 * vak op het dashboard en niet een regeltje.
 *
 * **Groei komt uit de rapporten, niet uit `players.overall_rating`.** Dat veld
 * is een momentopname zonder historie: het zegt hoe een speler er nú voor
 * staat, niet waar hij vandaan komt. Om te weten of iemand stijgt moet je twee
 * momenten vergelijken, en die staan in `reports` met hun `report_scores`.
 *
 * Het cijfer per rapport is het gemiddelde van de zes categorieën, maal tien —
 * dezelfde omrekening als op de kaart (zie CalculatePlayerCard), zodat een
 * stijging van "5" hier hetzelfde betekent als daar.
 */
class DevelopmentOverview
{
    /** Vanaf hoeveel punten een verandering het vermelden waard is. */
    public const DREMPEL = 3;

    /** Binnen hoeveel dagen een rapport "actueel" heet. */
    public const ACTUEEL_BINNEN_DAGEN = 30;

    /** @return array<string, mixed> */
    public function for(int $dagen = 30): array
    {
        $sinds = now()->subDays($dagen)->startOfDay();

        $veranderingen = $this->veranderingen($sinds);

        return [
            'days' => $dagen,
            'risers' => $veranderingen
                ->filter(fn (array $rij) => $rij['change'] >= self::DREMPEL)
                ->sortByDesc('change')
                ->take(3)
                ->values()
                ->all(),
            'fallers' => $veranderingen
                ->filter(fn (array $rij) => $rij['change'] <= -self::DREMPEL)
                ->sortBy('change')
                ->take(3)
                ->values()
                ->all(),
            'coverage' => $this->dekking(),
            'averageChange' => $veranderingen->isEmpty()
                ? null
                : (int) round($veranderingen->avg('change')),
        ];
    }

    /**
     * Per speler het verschil tussen het eerste en het laatste rapport in de
     * periode.
     *
     * Alleen spelers met **twee** rapporten in die periode: met één rapport valt
     * er niets te vergelijken, en een eerder rapport van maanden terug erbij
     * halen zou "groei deze maand" iets anders laten betekenen dan het zegt.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function veranderingen(Carbon $sinds): Collection
    {
        $rapporten = Report::query()
            ->whereDate('reported_on', '>=', $sinds->toDateString())
            ->with(['scores', 'player'])
            ->get()
            ->filter(fn (Report $rapport) => $rapport->player?->is_active)
            ->groupBy('player_id');

        return $rapporten
            ->filter(fn (Collection $vanSpeler) => $vanSpeler->count() >= 2)
            ->map(function (Collection $vanSpeler) {
                $opVolgorde = $vanSpeler->sortBy([['reported_on', 'asc'], ['id', 'asc']])->values();

                $eerste = $this->cijfer($opVolgorde->first());
                $laatste = $this->cijfer($opVolgorde->last());
                $speler = $opVolgorde->first()->player;

                return [
                    'id' => $speler->id,
                    'name' => $speler->full_name,
                    'from' => $eerste,
                    'to' => $laatste,
                    'change' => $laatste - $eerste,
                    'reports' => $opVolgorde->count(),
                ];
            })
            ->values();
    }

    /** Het gemiddelde van de categorieën van één rapport, op de schaal van de kaart. */
    protected function cijfer(Report $rapport): int
    {
        $scores = $rapport->scores->pluck('score');

        return $scores->isEmpty() ? 0 : (int) round($scores->avg() * 10);
    }

    /**
     * Hoeveel procent van de actieve spelers een actueel rapport heeft.
     *
     * Het getal waar het om draait: een school die dit boven de tachtig houdt
     * levert wat ze belooft.
     *
     * @return array{percentage: int|null, current: int, total: int}
     */
    protected function dekking(): array
    {
        $totaal = Player::active()->count();

        if ($totaal === 0) {
            return ['percentage' => null, 'current' => 0, 'total' => 0];
        }

        $grens = now()->subDays(self::ACTUEEL_BINNEN_DAGEN)->toDateString();

        $actueel = Player::active()
            ->whereHas('reports', fn ($q) => $q->whereDate('reported_on', '>=', $grens))
            ->count();

        return [
            'percentage' => (int) round($actueel / $totaal * 100),
            'current' => $actueel,
            'total' => $totaal,
        ];
    }
}
