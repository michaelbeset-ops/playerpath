<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\PlayerCardPresenter;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\PlayerCard\PlayerTimeline;
use App\Support\Progress\NextStep;
use App\Support\Rating\RatingEngine;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De groei van één speler over de tijd, plus de tijdlijn.
 *
 * Bereikbaar voor iedereen die de speler mag zien: trainer, eigenaar, de ouder
 * van dit kind en de speler zelf. Dat is dezelfde grens als de spelerskaart.
 */
class PlayerProgressController extends Controller
{
    public function __construct(
        protected PlayerProgress $progress,
        protected PlayerTimeline $timeline,
        protected GoalProgress $goals,
        protected PlayerCardPresenter $presenter,
        protected NextStep $nextStep,
        protected RatingEngine $engine,
    ) {}

    public function show(Player $player): Response
    {
        $this->authorize('view', $player);

        $doelen = $this->goals->forPlayer($player);

        return Inertia::render('players/Progress', [
            'player' => [
                'id' => $player->id,
                'name' => $player->full_name,
                'first_name' => $player->first_name,
                'position' => $player->position->label(),
                'overall_rating' => $player->overall_rating,
            ],
            // Dezelfde kaartgegevens als op de kaartpagina, zodat de uitleg
            // "Hoe werkt mijn rating?" hier hetzelfde zegt.
            'card' => $this->presenter->for($player),
            'level' => $this->engine->levelState($player),
            'progress' => $this->progress->for($player),
            'timeline' => $this->timeline->for($player),
            'quarter' => $this->timeline->quarterSummary($player),
            'goals' => $doelen,
            'nextStep' => $this->nextStep->for($player, $doelen),
            'canReport' => auth()->user()->can('createReport', $player),
        ]);
    }
}
