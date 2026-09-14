<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerCardPresenter;
use App\Support\PlayerCard\PlayerProgress;
use Inertia\Inertia;
use Inertia\Response;

class PlayerCardController extends Controller
{
    public function __construct(
        protected PlayerBadges $badges,
        protected PlayerProgress $progress,
        protected GoalProgress $goals,
        protected PlayerCardPresenter $presenter,
    ) {}

    public function show(Player $player): Response
    {
        $this->authorize('view', $player);

        // De inzetkaart: rapporten en doelen horen niet op deze pagina.
        $inzet = \App\Support\Rating\RatingSettings::for($player->school)->usesEffort();
        $laatste = $inzet ? null : $player->reports()->newestFirst()->with('trainer')->first();

        return Inertia::render('players/Card', [
            'player' => [
                'id' => $player->id,
                'name' => $player->full_name,
                'photo' => $player->photo_url,
                'position' => $player->position->label(),
                'position_key' => $player->position->value,
                'age' => $player->age,
                'overall_rating' => $player->overall_rating,
                'rated_at' => $player->rated_at?->format('d-m-Y'),
            ],
            // De kaart zelf, uit dezelfde bron als het dashboard en de deel-link.
            'card' => $this->presenter->for($player),
            // De foto zet je op de kaart zelf: de eigenaar, de ouders van dit
            // kind en het kind zelf. Zie PlayerPolicy::updatePhoto.
            'canPhoto' => auth()->user()->can('updatePhoto', $player),
            'reportCount' => $player->reports()->count(),
            'lastReport' => $laatste ? [
                'reported_on' => $laatste->reported_on->format('d-m-Y'),
                'trainer' => $laatste->trainer?->name,
                'note' => $laatste->note,
            ] : null,
            'canReport' => auth()->user()->can('createReport', $player),
            'goals' => $inzet ? [] : $this->goals->forPlayer($player),
            'share' => [
                'can' => auth()->user()->can('share', $player),
                'url' => $player->isShared() ? route('players.shared', $player->share_token) : null,
            ],
            // De kind-link: dezelfde poort als delen (eigenaar en ouders),
            // want ook hier beslis je wie de kaart zonder inlog te zien krijgt.
            'childLink' => [
                'can' => auth()->user()->can('share', $player),
                'url' => $player->hasChildLink() ? route('players.child', $player->child_token) : null,
            ],
            'badges' => $this->badges->for($player, $this->progress),
        ]);
    }
}
