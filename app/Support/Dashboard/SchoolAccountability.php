<?php

namespace App\Support\Dashboard;

use App\Enums\GoalStatus;
use App\Models\Attendance;
use App\Models\Goal;
use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use Carbon\CarbonImmutable;

/**
 * Wat een school over een periode kan laten zien.
 *
 * Bedoeld voor het gesprek dat een schooleigenaar buiten zijn eigen muren
 * voert: met een ouderavond, met een vereniging, of met een gemeente die wil
 * weten wat er op haar velden gebeurt. Verschillende steden stellen inmiddels
 * eisen aan commerciële voetbalscholen, en het verwijt is dat er dromen worden
 * verkocht. Cijfers over wat er feitelijk is vastgelegd zijn daar het
 * tegenargument.
 *
 * Drie regels, dezelfde als op het dashboard:
 *
 * 1. **Alleen cijfers die echt bestaan.** Liever een leeg vak met uitleg dan
 *    een getal dat nergens op slaat.
 * 2. **Opkomst telt alleen wat de trainer echt afvinkte.** "Niet afgevinkt" is
 *    geen "afwezig".
 * 3. **Geen enkele vergelijking tussen kinderen.** Alles hier is een totaal of
 *    een gemiddelde over de school, nooit een rangorde.
 */
class SchoolAccountability
{
    /** @return array<string, mixed> */
    public function for(CarbonImmutable $vanaf, CarbonImmutable $tot): array
    {
        $actief = Player::query()->where('is_active', true);

        $spelers = (clone $actief)->count();
        $metDoel = Goal::active()->distinct('player_id')->count('player_id');

        // whereDate en niet whereBetween met een datumstring: reported_on wordt
        // met een tijdcomponent opgeslagen ("2026-09-06 00:00:00"), en dan valt
        // een rapport van de laatste dag lexicografisch buiten de bovengrens.
        $inPeriode = fn () => Report::query()
            ->whereDate('reported_on', '>=', $vanaf)
            ->whereDate('reported_on', '<=', $tot);

        $rapporten = $inPeriode()->count();
        $spelersMetRapport = $inPeriode()->distinct('player_id')->count('player_id');

        $trainingen = Training::query()->whereBetween('starts_at', [$vanaf, $tot]);

        return [
            'period' => [
                'from' => $vanaf->format('d-m-Y'),
                'to' => $tot->format('d-m-Y'),
                'label' => $vanaf->translatedFormat('j F Y').' t/m '.$tot->translatedFormat('j F Y'),
            ],
            'players' => $spelers,
            'reports' => $rapporten,
            // Het cijfer dat er echt toe doet: hoeveel kinderen zijn er
            // daadwerkelijk gevolgd. Honderd rapporten voor vijf spelers zegt
            // iets heel anders dan honderd rapporten voor vijftig.
            'playersWithReport' => $spelersMetRapport,
            'coverage' => $spelers === 0 ? null : (int) round($spelersMetRapport / $spelers * 100),
            'playersWithGoal' => $metDoel,
            'goalCoverage' => $spelers === 0 ? null : (int) round($metDoel / $spelers * 100),
            'goalsAchieved' => Goal::query()
                ->where('status', GoalStatus::Achieved->value)
                ->whereBetween('achieved_at', [$vanaf, $tot])
                ->count(),
            'trainings' => (clone $trainingen)->whereNull('cancelled_at')->count(),
            'cancelled' => (clone $trainingen)->whereNotNull('cancelled_at')->count(),
            'attendance' => $this->opkomst($vanaf, $tot),
            'development' => $this->ontwikkeling($vanaf, $tot),
        ];
    }

    /**
     * Opkomst over de periode: aanwezig gedeeld door wat er is afgevinkt.
     *
     * @return array{percentage: int|null, present: int, recorded: int}
     */
    private function opkomst(CarbonImmutable $vanaf, CarbonImmutable $tot): array
    {
        $afgevinkt = Attendance::query()
            ->whereNotNull('status')
            ->whereHas('training', fn ($q) => $q->whereBetween('starts_at', [$vanaf, $tot]));

        $totaal = (clone $afgevinkt)->count();
        $aanwezig = (clone $afgevinkt)->where('status', 'present')->count();

        return [
            'percentage' => $totaal === 0 ? null : (int) round($aanwezig / $totaal * 100),
            'present' => $aanwezig,
            'recorded' => $totaal,
        ];
    }

    /**
     * De gemiddelde ontwikkeling over de periode.
     *
     * Per speler het verschil tussen zijn eerste en zijn laatste rapport in de
     * periode, en daarvan het gemiddelde. Spelers met maar één rapport tellen
     * niet mee: uit één meting valt geen ontwikkeling af te lezen.
     *
     * @return array{average: float|null, measured: int, improved: int}
     */
    private function ontwikkeling(CarbonImmutable $vanaf, CarbonImmutable $tot): array
    {
        $rapporten = Report::query()
            ->with('scores')
            ->whereDate('reported_on', '>=', $vanaf)
            ->whereDate('reported_on', '<=', $tot)
            ->orderBy('reported_on')
            ->orderBy('id')
            ->get()
            ->groupBy('player_id');

        $verschillen = [];

        foreach ($rapporten as $vanSpeler) {
            if ($vanSpeler->count() < 2) {
                continue;
            }

            $eerste = $this->gemiddelde($vanSpeler->first());
            $laatste = $this->gemiddelde($vanSpeler->last());

            if ($eerste !== null && $laatste !== null) {
                $verschillen[] = $laatste - $eerste;
            }
        }

        return [
            'average' => $verschillen === [] ? null : round(array_sum($verschillen) / count($verschillen), 1),
            'measured' => count($verschillen),
            'improved' => count(array_filter($verschillen, fn (float|int $v) => $v > 0)),
        ];
    }

    private function gemiddelde(Report $rapport): ?float
    {
        $cijfers = $rapport->scoresByCategory();

        return $cijfers === [] ? null : array_sum($cijfers) / count($cijfers) * 10;
    }
}
