<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\StoreReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\Player;
use App\Models\Report;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Reports\ReportOutcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected CalculatePlayerCard $calculator,
        protected GoalProgress $goals,
        protected ReportOutcome $outcome,
    ) {}

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
        // De stand vóór het opslaan, zodat de kaartpagina kan laten zien wát er
        // veranderde. Dat is het moment waar de trainer het voor doet.
        $voor = $this->outcome->snapshot($player);

        $storeReport->handle(
            player: $player,
            trainer: $request->user(),
            scores: $request->scores(),
            note: $request->validated('note'),
        );

        $wijziging = $this->outcome->changes($voor, $player->refresh());
        $wijziging['next'] = $this->volgendeSpeler($request, $player);

        return redirect()
            ->route('players.card', $player)
            ->with('status', 'Het rapport is opgeslagen en de spelerskaart is bijgewerkt.')
            ->with('reportResult', $wijziging);
    }

    /**
     * De eerstvolgende speler die vandaag nog geen rapport heeft.
     *
     * Zo loopt een trainer na een training zijn groep af zonder telkens terug
     * te hoeven naar de lijst. Alfabetisch, dus de volgorde is voorspelbaar.
     *
     * @return array{id: int, name: string}|null
     */
    protected function volgendeSpeler(Request $request, Player $huidige): ?array
    {
        $volgende = Player::query()
            ->active()
            ->whereKeyNot($huidige->id)
            ->whereDoesntHave('reports', fn ($query) => $query->whereDate('reported_on', now()->toDateString()))
            ->orderBy('first_name')
            ->get()
            ->first(fn (Player $speler) => $request->user()->can('createReport', $speler));

        return $volgende === null ? null : [
            'id' => $volgende->id,
            'name' => $volgende->full_name,
        ];
    }
}
