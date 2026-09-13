<?php

namespace App\Support\Progress;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\CalculatePlayerCard;
use Carbon\CarbonImmutable;

/**
 * Wat er de afgelopen maand met een speler gebeurd is, in één bericht.
 *
 * Dit is het moment waarop een ouder beslist of hij volgend seizoen doorgaat.
 * Een commerciële voetbalschool vraagt zeven keer zoveel per training als een
 * vereniging; deze mail is het antwoord op de vraag waar dat geld heen gaat.
 *
 * Twee regels die het bericht eerlijk houden:
 *
 * 1. **Geen bericht zonder inhoud.** Is er die maand niets gebeurd - geen
 *    rapport, geen doel, geen training - dan gaat er niets uit. Een maandelijkse
 *    mail die vier keer achter elkaar "geen nieuws" zegt, leert de ouder hem
 *    weg te klikken, en dan mist hij ook de maand waarin het er wel toe doet.
 * 2. **Alleen de eigen speler.** Er staat nergens een vergelijking met andere
 *    kinderen in. Zie CLAUDE.md over waarom er geen ranglijsten zijn.
 */
class MonthlyDigest
{
    public function __construct(private readonly GoalProgress $goals) {}

    /**
     * @return array<string, mixed>|null null als er niets te melden valt
     */
    public function for(Player $player, ?CarbonImmutable $tot = null): ?array
    {
        $tot ??= CarbonImmutable::now();
        $vanaf = $tot->subMonth();

        $rapporten = $player->reports()
            ->with('scores')
            ->whereDate('reported_on', '>=', $vanaf)
            ->orderBy('reported_on')
            ->get();

        $doelen = collect($this->goals->forPlayer($player))
            ->filter(fn (array $doel) => $doel['status'] === 'active'
                || ($doel['status'] === 'achieved' && $this->inPeriode($player, $doel, $vanaf)))
            ->values()
            ->all();

        $trainingen = Training::query()
            ->whereIn('group_id', $player->groups()->pluck('groups.id'))
            ->whereNull('cancelled_at')
            ->where('starts_at', '>', $tot)
            ->orderBy('starts_at')
            ->first();

        $aanwezig = $player->attendances()
            ->whereHas('training', fn ($q) => $q->whereBetween('starts_at', [$vanaf, $tot]))
            ->where('status', 'present')
            ->count();

        // Niets gebeurd, niets te melden.
        if ($rapporten->isEmpty() && $doelen === [] && $aanwezig === 0) {
            return null;
        }

        return [
            'period' => $vanaf->translatedFormat('j F').' t/m '.$tot->translatedFormat('j F Y'),
            'reports' => $rapporten->count(),
            'attended' => $aanwezig,
            'rating' => $player->overall_rating,
            'delta' => $this->groei($rapporten),
            'highlight' => $this->grootsteStijger($rapporten),
            'goals' => $doelen,
            'achievedGoals' => collect($doelen)->where('status', 'achieved')->count(),
            'nextTraining' => $trainingen === null ? null : [
                'date' => $trainingen->starts_at->translatedFormat('l j F'),
                'time' => $trainingen->starts_at->format('H:i'),
                'location' => $trainingen->location,
            ],
        ];
    }

    /**
     * Het verschil tussen het eerste en het laatste rapport van de periode.
     *
     * Bewust niet het kaartcijfer van een maand geleden: dat bewaren we niet,
     * en het reconstrueren zou een schatting opleveren die er precies uitziet
     * als een meting.
     */
    private function groei($rapporten): ?int
    {
        if ($rapporten->count() < 2) {
            return null;
        }

        $eerste = $this->gemiddelde($rapporten->first());
        $laatste = $this->gemiddelde($rapporten->last());

        return $eerste === null || $laatste === null ? null : $laatste - $eerste;
    }

    /** De categorie die er deze periode het meest op vooruit ging. */
    private function grootsteStijger($rapporten): ?array
    {
        if ($rapporten->count() < 2) {
            return null;
        }

        $eerste = $rapporten->first()->scoresByCategory();
        $laatste = $rapporten->last()->scoresByCategory();

        $beste = null;

        foreach ($laatste as $categorie => $cijfer) {
            if (! isset($eerste[$categorie])) {
                continue;
            }

            $verschil = $cijfer - $eerste[$categorie];

            if ($verschil > 0 && ($beste === null || $verschil > $beste['delta'])) {
                $beste = [
                    'category' => $categorie,
                    'label' => $rapporten->last()->scores->firstWhere('category.value', $categorie)?->category->label() ?? $categorie,
                    'delta' => $verschil,
                    // Naar boven, net als op de kaart; zie CalculatePlayerCard.
                    'now' => CalculatePlayerCard::afronden($cijfer * 10),
                ];
            }
        }

        return $beste;
    }

    private function gemiddelde(Report $rapport): ?int
    {
        $cijfers = $rapport->scoresByCategory();

        return $cijfers === [] ? null : CalculatePlayerCard::afronden(array_sum($cijfers) / count($cijfers) * 10);
    }

    /** Is dit doel in de afgelopen periode gehaald? */
    private function inPeriode(Player $player, array $doel, CarbonImmutable $vanaf): bool
    {
        $goal = Goal::query()
            ->whereKey($doel['id'])
            ->where('status', GoalStatus::Achieved->value)
            ->first();

        return $goal?->achieved_at !== null && $goal->achieved_at->greaterThanOrEqualTo($vanaf);
    }
}
