<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\StoreReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Reports\ReportOutcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De snelle invulflow: één training, alle kinderen achter elkaar.
 *
 * Het losse invulscherm (`ReportController::create`) is er voor "ik wil één
 * speler beoordelen". Dit is het andere geval, en veruit het vaakste: de
 * training is net afgelopen, de trainer staat nog op het veld, en hij wil er
 * acht doen zonder telkens terug naar een lijst.
 *
 * Vijf dingen die dit snel houden en die je niet moet weghalen:
 *
 * 1. **Opslaan gaat direct door naar de volgende speler.** Geen tussenscherm,
 *    geen bevestiging. Wie op de kaartpagina uitkomt is zijn ritme kwijt.
 * 2. **De volgorde ligt vast** (de spelers van de training, alfabetisch), zodat
 *    "speler 3 van 8" ergens op slaat en je weet hoe lang je nog bezig bent.
 * 3. **Overslaan mag.** Een kind dat halverwege naar huis ging beoordeel je
 *    niet; die blijft gewoon open staan en het herinneringsblok telt hem mee.
 * 4. **Stoppen mag ook.** Elk rapport is bij het opslaan al binnen; er is geen
 *    concept dat je kunt kwijtraken. Kom je later terug, dan begint de flow bij
 *    de eerste die nog open staat.
 * 5. **Dezelfde flow voor de eigenaar.** Die geeft bij een kleine school zelf
 *    training; twee keer hetzelfde bouwen betekent dat er één van de twee
 *    achterloopt. De poort is `recordAttendance` — wie mag afvinken, mag ook
 *    beoordelen.
 *
 * Wat er onderweg veranderde wordt in de sessie verzameld en aan het eind in
 * één samenvatting getoond. Per speler vieren zou acht keer hetzelfde blok
 * opleveren, en dan kijk je er na de tweede niet meer naar.
 */
class TrainingReportController extends Controller
{
    public function __construct(
        protected CalculatePlayerCard $calculator,
        protected GoalProgress $goals,
        protected ReportOutcome $outcome,
    ) {}

    /** De sessiesleutel waarin de wijzigingen van deze ronde staan. */
    protected function sleutel(Training $training): string
    {
        return 'quickReports.'.$training->id;
    }

    public function show(Request $request, Training $training): Response|RedirectResponse
    {
        $this->authorize('recordAttendance', $training);

        $user = $request->user();
        $spelers = $this->spelers($training, $request);

        if ($spelers->isEmpty()) {
            return redirect()
                ->route('trainings.show', $training)
                ->with('status', 'Er zijn geen spelers om te beoordelen bij deze training.');
        }

        $gedaan = $this->gedaan($training, $spelers);

        // Welke speler staat er nu? De gevraagde, anders de eerste die nog
        // open staat. Is alles gedaan, dan is de ronde klaar.
        $gevraagd = $request->integer('speler');

        $huidige = $spelers->firstWhere('id', $gevraagd)
            ?? $spelers->first(fn (Player $speler) => ! $gedaan->has($speler->id));

        if ($huidige === null) {
            return redirect()->route('trainings.reports.summary', $training);
        }

        $vorige = $huidige->reports()->newestFirst()->with('scores')->first();
        $positie = $spelers->search(fn (Player $speler) => $speler->id === $huidige->id);

        return Inertia::render('reports/Quick', [
            'training' => [
                'id' => $training->id,
                'group' => $training->label(),
                'date' => $training->starts_at->translatedFormat('l j F'),
                'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
            ],
            // De hele reeks, zodat het scherm "speler 3 van 8" kan tonen en je
            // met één tik naar iemand anders kunt springen.
            'roster' => $spelers->map(fn (Player $speler) => [
                'id' => $speler->id,
                'name' => $speler->full_name,
                'first_name' => $speler->first_name,
                'photo' => $speler->photo_url,
                'done' => $gedaan->has($speler->id),
            ])->values(),
            'position' => $positie + 1,
            'total' => $spelers->count(),
            'doneCount' => $gedaan->count(),
            'player' => [
                'id' => $huidige->id,
                'name' => $huidige->full_name,
                'photo' => $huidige->photo_url,
                'position' => $huidige->position->label(),
                'age' => $huidige->age,
                'overall_rating' => $huidige->overall_rating,
                'done' => $gedaan->has($huidige->id),
            ],
            'categories' => $this->calculator->breakdown($huidige),
            // Voorinvullen met het vorige rapport: de trainer past alleen aan
            // wat veranderd is. Dat is de kern van het 30-seconden-scherm.
            'previousScores' => $vorige?->scoresByCategory() ?? (object) [],
            'previousReportedOn' => $vorige?->reported_on->format('d-m-Y'),
            'goals' => collect($this->goals->forPlayer($huidige))
                ->where('status', 'active')
                ->keyBy('category')
                ->map(fn ($d) => ['target' => $d['target'], 'current' => $d['current'], 'on_track' => $d['on_track']]),
            // Pijltjes: de speler ervoor en erna in de reeks, ook als die al
            // een rapport heeft. Terugbladeren om iets na te kijken hoort te
            // kunnen zonder de flow te verlaten.
            'prev' => $positie > 0 ? $spelers[$positie - 1]->id : null,
            'next' => $positie + 1 < $spelers->count() ? $spelers[$positie + 1]->id : null,
        ]);
    }

    public function store(StoreReportRequest $request, Training $training, Player $player, StoreReport $storeReport): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);

        $voor = $this->outcome->snapshot($player);

        $storeReport->handle(
            player: $player,
            trainer: $request->user(),
            scores: $request->scores(),
            note: $request->validated('note'),
            // De dag van de training, niet die van vandaag. Wie 's avonds laat
            // afsluit hoort geen rapport van morgen te krijgen — en het is de
            // datum waarop het herinneringsblok "gedaan" telt.
            reportedOn: $training->starts_at->toDateString(),
        );

        $wijziging = $this->outcome->changes($voor, $player->refresh());

        // Verzamelen voor de samenvatting aan het eind. Per speler vieren zou
        // acht keer hetzelfde blok opleveren.
        $verzameld = $request->session()->get($this->sleutel($training), []);
        $verzameld[$player->id] = $wijziging;
        $request->session()->put($this->sleutel($training), $verzameld);

        $volgende = $this->volgende($training, $request, $player);

        if ($volgende === null) {
            return redirect()->route('trainings.reports.summary', $training);
        }

        return redirect()
            ->route('trainings.reports.show', ['training' => $training, 'speler' => $volgende]);
    }

    /**
     * De samenvatting: wat leverde deze ronde op.
     *
     * De sessie wordt hier leeggehaald, zodat een tweede ronde bij dezelfde
     * training niet de resultaten van de eerste erbij toont.
     */
    public function summary(Request $request, Training $training): Response
    {
        $this->authorize('recordAttendance', $training);

        $verzameld = $request->session()->pull($this->sleutel($training), []);

        $spelers = $this->spelers($training, $request);
        $gedaan = $this->gedaan($training, $spelers);

        return Inertia::render('reports/QuickSummary', [
            'training' => [
                'id' => $training->id,
                'group' => $training->label(),
                'date' => $training->starts_at->translatedFormat('l j F'),
            ],
            'results' => array_values($verzameld),
            'total' => $spelers->count(),
            'doneCount' => $gedaan->count(),
            'openCount' => $spelers->count() - $gedaan->count(),
            'open' => $spelers
                ->reject(fn (Player $speler) => $gedaan->has($speler->id))
                ->map(fn (Player $speler) => ['id' => $speler->id, 'name' => $speler->full_name])
                ->values(),
        ]);
    }

    /**
     * De spelers van deze training die deze gebruiker mag beoordelen.
     *
     * De policy-controle staat er expliciet bij: `recordAttendance` zegt dat je
     * bij deze training mag afvinken, `createReport` dat je over dit kind mag
     * schrijven. Twee sloten, net als overal.
     *
     * @return Collection<int, Player>
     */
    protected function spelers(Training $training, Request $request): Collection
    {
        return $training->expectedPlayers()
            ->filter(fn (Player $speler) => $request->user()->can('createReport', $speler))
            ->values();
    }

    /**
     * Wie er al een rapport heeft op de dag van deze training.
     *
     * Rapporten hangen bewust niet aan een training — een trainer schrijft over
     * een speler, niet over een sessie. De datum is het enige eerlijke verband,
     * en dezelfde regel als in ReportPrompts.
     *
     * @param  Collection<int, Player>  $spelers
     * @return Collection<int, mixed>
     */
    protected function gedaan(Training $training, Collection $spelers): Collection
    {
        return Report::query()
            ->whereIn('player_id', $spelers->pluck('id'))
            ->whereDate('reported_on', $training->starts_at->toDateString())
            ->pluck('player_id')
            ->unique()
            ->flip();
    }

    /**
     * De volgende speler die nog open staat, ná deze.
     *
     * Vooruit kijken vanaf de huidige positie, en pas daarna vanaf het begin:
     * zo pikt de flow aan het eind de spelers op die je hebt overgeslagen, in
     * plaats van je halverwege terug te sturen.
     */
    protected function volgende(Training $training, Request $request, Player $huidige): ?int
    {
        $spelers = $this->spelers($training, $request);
        $gedaan = $this->gedaan($training, $spelers);

        $positie = $spelers->search(fn (Player $speler) => $speler->id === $huidige->id);
        $vanaf = $positie === false ? 0 : $positie + 1;

        $volgorde = $spelers->slice($vanaf)->concat($spelers->slice(0, $vanaf));

        return $volgorde->first(fn (Player $speler) => ! $gedaan->has($speler->id))?->id;
    }
}
