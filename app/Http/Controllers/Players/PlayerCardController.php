<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerProgress;
use Inertia\Inertia;
use Inertia\Response;

class PlayerCardController extends Controller
{
    public function __construct(
        protected CalculatePlayerCard $calculator,
        protected PlayerBadges $badges,
        protected PlayerProgress $progress,
        protected GoalProgress $goals,
    ) {}

    public function show(Player $player): Response
    {
        $this->authorize('view', $player);

        $laatste = $player->reports()->newestFirst()->with('trainer')->first();

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
            'categories' => $this->calculator->breakdown($player),
            'reportCount' => $player->reports()->count(),
            'lastReport' => $laatste ? [
                'reported_on' => $laatste->reported_on->format('d-m-Y'),
                'trainer' => $laatste->trainer?->name,
                'note' => $laatste->note,
            ] : null,
            'canReport' => auth()->user()->can('createReport', $player),
            'level' => $this->badges->level($player->overall_rating),
            'goals' => $this->goals->forPlayer($player),
            'share' => [
                'can' => auth()->user()->can('share', $player),
                'url' => $player->isShared() ? route('players.shared', $player->share_token) : null,
            ],
            'badges' => $this->badges->for($player, $this->progress),
        ]);
    }
}
