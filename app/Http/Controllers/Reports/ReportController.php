<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\StoreReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\Group;
use App\Models\Player;
use App\Models\Report;
use App\Support\Dashboard\SchoolDashboard;
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

        // Vanuit een training kom je hier met een groep in de URL: dan gaat het
        // om de spelers die je zojuist voor je had staan.
        $groep = $request->integer('group');

        $players = Player::query()
            ->active()
            ->visibleTo($request->user())
            ->when($groep > 0, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($groep)))
            ->orderBy('first_name')
            ->get()
            ->map(function (Player $player) {
                $laatste = $player->reports()->newestFirst()->value('reported_on');

                return [
                    'id' => $player->id,
                    'name' => $player->full_name,
                    'position' => $player->position->label(),
                    'age' => $player->age,
                    'overall_rating' => $player->overall_rating,
                    'last_report_on' => $laatste?->format('d-m-Y'),
                    // Hoe lang geleden, zodat het scherm kan laten zien waar het
                    // stilvalt. De grens ligt op 30 dagen, dezelfde als het
                    // aandacht-blok op het dashboard: één begrip van "te lang".
                    'days_since_report' => $laatste?->startOfDay()->diffInDays(now()->startOfDay()),
                ];
            });

        return Inertia::render('reports/Index', [
            'players' => $players,
            // De groep uit de scope: staat hij niet in deze school, dan is er
            // niets om op te filteren en blijft de naam leeg.
            'group' => $groep > 0
                ? Group::whereKey($groep)->value('name')
                : null,
            // Dezelfde grens als het aandacht-blok op het dashboard: "te lang
            // geleden" hoort in de hele app hetzelfde te betekenen.
            'staleAfterDays' => SchoolDashboard::AANDACHT_NA_DAGEN,
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
            ->visibleTo($request->user())
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
