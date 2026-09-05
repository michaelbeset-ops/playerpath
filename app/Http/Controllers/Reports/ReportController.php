<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\StoreReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\Player;
use App\Models\Report;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\CalculatePlayerCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(protected CalculatePlayerCard $calculator, protected GoalProgress $goals) {}

    /** Wie ga je beoordelen? De lijst is bewust kort en direct klikbaar. */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Report::class);

        $players = Player::query()
            ->active()
            ->orderBy('first_name')
            ->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->full_name,
                'position' => $player->position->label(),
                'age' => $player->age,
                'overall_rating' => $player->overall_rating,
                'last_report_on' => $player->reports()->newestFirst()->value('reported_on')?->format('d-m-Y'),
            ]);

        return Inertia::render('reports/Index', [
            'players' => $players,
        ]);
    }

    /** Het 30-seconden-scherm. */
    public function create(Player $player): Response
    {
        $this->authorize('createReport', $player);

        $vorige = $player->reports()->newestFirst()->with('scores')->first();

        return Inertia::render('reports/Create', [
            'player' => [
                'id' => $player->id,
                'name' => $player->full_name,
                'position' => $player->position->label(),
                'age' => $player->age,
                'overall_rating' => $player->overall_rating,
            ],
            'categories' => $this->calculator->breakdown($player),
            // Voorinvullen met het vorige rapport scheelt de trainer de meeste
            // tikken: hij past alleen aan wat veranderd is.
            'previousScores' => $vorige?->scoresByCategory() ?? (object) [],
            'previousReportedOn' => $vorige?->reported_on->format('d-m-Y'),
            // Actieve doelen per categorie: een klein 'op koers' naast het cijfer,
            // zonder het 30-seconden-ritme te breken.
            'goals' => collect($this->goals->forPlayer($player))
                ->where('status', 'active')
                ->keyBy('category')
                ->map(fn ($d) => ['target' => $d['target'], 'current' => $d['current'], 'on_track' => $d['on_track']]),
        ]);
    }

    public function store(StoreReportRequest $request, Player $player, StoreReport $storeReport): RedirectResponse
    {
        $storeReport->handle(
            player: $player,
            trainer: $request->user(),
            scores: $request->scores(),
            note: $request->validated('note'),
        );

        return redirect()
            ->route('players.card', $player)
            ->with('status', 'Het rapport is opgeslagen en de spelerskaart is bijgewerkt.');
    }
}
