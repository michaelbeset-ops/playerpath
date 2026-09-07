<?php

namespace App\Support\Dashboard;

use App\Models\Player;
use App\Models\User;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerCardPresenter;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\PlayerCard\PlayerTimeline;
use App\Support\Progress\NextStep;

/**
 * Het dashboard van een speler met een eigen inlog.
 *
 * Een kind komt niet voor rekeningen of een inschrijfformulier; dat regelen
 * zijn ouders. Het komt voor drie dingen, in deze volgorde: **mijn kaart**,
 * **hoe ga ik vooruit** en **wanneer is de volgende training**. Meer staat er
 * niet, en wat er staat is speels: grote cijfers, een level om naartoe te
 * werken, en één ding om aan te werken in plaats van een lijst kritiek.
 *
 * Alles komt uit dezelfde bronnen als de kaart- en voortgangspagina
 * (`PlayerCardPresenter`, `PlayerProgress`, `NextStep`), zodat het dashboard
 * nooit iets anders zegt dan de pagina erachter.
 */
class PlayerDashboard
{
    public function __construct(
        protected PlayerCardPresenter $presenter,
        protected PlayerProgress $progress,
        protected PlayerTimeline $timeline,
        protected PlayerBadges $badges,
        protected GoalProgress $goals,
        protected NextStep $nextStep,
        protected FamilyDashboard $family,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $user, Player $speler): array
    {
        $voortgang = $this->progress->for($speler);
        $doelen = $this->goals->forPlayer($speler);

        return [
            'player' => [
                'id' => $speler->id,
                'first_name' => $speler->first_name,
            ],
            'card' => $this->presenter->for($speler),
            'quarter' => $this->timeline->quarterSummary($speler),
            'categories' => array_map(fn (array $categorie) => [
                'category' => $categorie['category'],
                'label' => $categorie['label'],
                'last' => $categorie['last'],
                'delta' => $categorie['delta'],
                'trend' => $categorie['trend'],
            ], $voortgang['categories']),
            'hasEnoughData' => $voortgang['hasEnoughData'],
            'nextStep' => $this->nextStep->for($speler, $doelen),
            // De eerstvolgende badge die nog niet binnen is: iets om naartoe
            // te werken, naast het level.
            'nextBadge' => collect($this->badges->for($speler, $this->progress))
                ->first(fn (array $badge) => ! $badge['earned']),
            'nextTraining' => $this->family->upcomingTrainings($user, [$speler->id], limiet: 1)[0] ?? null,
        ];
    }
}
