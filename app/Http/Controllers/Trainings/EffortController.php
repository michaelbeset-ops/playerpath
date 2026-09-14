<?php

namespace App\Http\Controllers\Trainings;

use App\Actions\Trainings\RecordEffort;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\EffortRating;
use App\Models\Player;
use App\Models\Product;
use App\Models\Training;
use App\Support\Progress\CourseProgress;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\RatingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De inzetflow: na de training alle kinderen achter elkaar, zonder cijfers.
 *
 * Het broertje van de snelle rapportflow (TrainingReportController), voor
 * scholen met de inzetkaart. Per kind drie tikken: aanwezig, hoe hard het
 * werkte, hoe het luisterde. Samen ongeveer twintig seconden.
 *
 * Dezelfde afspraken als bij de rapporten: opslaan gaat direct door naar de
 * volgende, de volgorde ligt vast, overslaan en stoppen mogen, en aan het eind
 * staat één samenvatting. De poort is `recordAttendance` op de training plus
 * `createReport` op het kind.
 */
class EffortController extends Controller
{
    public function __construct(protected RatingEngine $engine) {}

    protected function sleutel(Training $training): string
    {
        return 'quickEffort.'.$training->id;
    }

    public function show(Request $request, Training $training): Response|RedirectResponse
    {
        $this->authorize('recordAttendance', $training);

        $settings = RatingSettings::for($request->user()->school);

        if (! $settings->usesEffort()) {
            return redirect()->route('trainings.reports.show', $training);
        }

        $spelers = $this->spelers($training, $request);

        if ($spelers->isEmpty()) {
            return redirect()
                ->route('trainings.show', $training)
                ->with('status', 'Er zijn geen spelers om inzetpunten te geven bij deze training.');
        }

        $standen = $this->standen($training);

        $huidige = $spelers->firstWhere('id', $request->integer('speler'))
            ?? $spelers->first(fn (Player $speler) => ! $standen['done']->has($speler->id));

        if ($huidige === null) {
            return redirect()->route('trainings.effort.summary', $training);
        }

        $positie = $spelers->search(fn (Player $speler) => $speler->id === $huidige->id);
        $aanwezigheid = $standen['attendance']->get($huidige->id);
        $inzet = $standen['ratings']->get($huidige->id);

        return Inertia::render('trainings/EffortQuick', [
            'training' => $this->trainingProps($training),
            'roster' => $spelers->map(fn (Player $speler) => [
                'id' => $speler->id,
                'name' => $speler->full_name,
                'first_name' => $speler->first_name,
                'photo' => $speler->photo_url,
                'done' => $standen['done']->has($speler->id),
            ])->values(),
            'position' => $positie + 1,
            'total' => $spelers->count(),
            'doneCount' => $spelers->filter(fn (Player $s) => $standen['done']->has($s->id))->count(),
            'player' => [
                'id' => $huidige->id,
                'name' => $huidige->full_name,
                'first_name' => $huidige->first_name,
                'photo' => $huidige->photo_url,
                'position' => $huidige->position->label(),
                'age' => $huidige->age,
                'level' => $this->engine->levelState($huidige),
                'done' => $standen['done']->has($huidige->id),
            ],
            // Wat er al staat. Nog niets: aanwezig staat voorgekozen, want
            // wie op de lijst staat is er meestal.
            'current' => [
                'present' => $aanwezigheid?->status === AttendanceStatus::Absent ? false : true,
                'effort' => $inzet?->effort,
                'attitude' => $inzet?->attitude,
                'note' => $inzet?->note,
            ],
            'effortLevels' => $settings->effortLevels(),
            'attitudeLevels' => $settings->attitudeLevels(),
            'attendancePoints' => $settings->xpForAttendance(),
            'prev' => $positie > 0 ? $spelers[$positie - 1]->id : null,
            'next' => $positie + 1 < $spelers->count() ? $spelers[$positie + 1]->id : null,
        ]);
    }

    public function store(Request $request, Training $training, Player $player, RecordEffort $record): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);

        $settings = RatingSettings::for($request->user()->school);
        abort_unless($settings->usesEffort(), 404);
        abort_unless($this->spelers($training, $request)->contains('id', $player->id), 404);

        $data = $request->validate([
            'present' => ['required', 'boolean'],
            'effort' => ['nullable', 'string', Rule::in(array_column($settings->effortLevels(), 'key'))],
            'attitude' => ['nullable', 'string', Rule::in(array_column($settings->attitudeLevels(), 'key'))],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'present' => 'Aanwezig',
            'effort' => 'De inzet',
            'attitude' => 'De houding',
            'note' => 'De notitie',
        ]);

        $aanwezig = (bool) $data['present'];
        $voor = $this->engine->levelState($player);

        $rating = $record->handle(
            $training,
            $player,
            $aanwezig,
            $aanwezig ? ($data['effort'] ?? null) : null,
            $aanwezig ? ($data['attitude'] ?? null) : null,
            $aanwezig ? ($data['note'] ?? null) : null,
            $request->user(),
        );

        $na = $this->engine->levelState($player->refresh());

        // Verzamelen voor de samenvatting aan het eind, net als bij de rapporten.
        $verzameld = $request->session()->get($this->sleutel($training), []);
        $verzameld[$player->id] = [
            'id' => $player->id,
            'name' => $player->full_name,
            'photo' => $player->photo_url,
            'present' => $aanwezig,
            'points' => $aanwezig ? $settings->xpForAttendance() + ($rating?->points() ?? 0) : 0,
            'labels' => $rating === null ? [] : array_values(array_filter([
                $settings->effortLevel($rating->effort)['label'] ?? null,
                $settings->attitudeLevel($rating->attitude)['label'] ?? null,
            ])),
            'level' => $na['label'],
            'level_key' => $na['key'],
            'level_up' => $na['key'] !== $voor['key'] && $na['xp'] > $voor['xp'] ? $na['label'] : null,
        ];
        $request->session()->put($this->sleutel($training), $verzameld);

        $volgende = $this->volgende($training, $request, $player);

        if ($volgende === null) {
            return redirect()->route('trainings.effort.summary', $training);
        }

        return redirect()->route('trainings.effort.show', ['training' => $training, 'speler' => $volgende]);
    }

    public function summary(Request $request, Training $training): Response|RedirectResponse
    {
        $this->authorize('recordAttendance', $training);

        if (! RatingSettings::for($request->user()->school)->usesEffort()) {
            return redirect()->route('trainings.reports.summary', $training);
        }

        $verzameld = $request->session()->pull($this->sleutel($training), []);
        $spelers = $this->spelers($training, $request);
        $gedaan = $this->standen($training)['done'];
        $open = $spelers->reject(fn (Player $speler) => $gedaan->has($speler->id));

        return Inertia::render('trainings/EffortSummary', [
            'training' => $this->trainingProps($training),
            'results' => array_values($verzameld),
            'total' => $spelers->count(),
            'doneCount' => $spelers->count() - $open->count(),
            'open' => $open->map(fn (Player $speler) => ['id' => $speler->id, 'name' => $speler->full_name])->values(),
            'course' => self::cursus($training),
        ]);
    }

    /**
     * Hoort deze training bij een cursus of blok? Dan kan de trainer daar het
     * begin- en eindniveau vastleggen.
     *
     * @return array{id: int, name: string}|null
     */
    public static function cursus(Training $training): ?array
    {
        $productId = $training->group?->product_id;

        if ($productId === null) {
            return null;
        }

        $product = Product::query()->find($productId);

        return $product !== null && CourseProgress::eligible($product)
            ? ['id' => $product->id, 'name' => $product->name]
            : null;
    }

    /** @return array<string, mixed> */
    protected function trainingProps(Training $training): array
    {
        return [
            'id' => $training->id,
            'group' => $training->label(),
            'date' => $training->starts_at->translatedFormat('l j F'),
            'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
        ];
    }

    /**
     * De spelers van deze training die deze gebruiker mag belonen.
     *
     * @return Collection<int, Player>
     */
    protected function spelers(Training $training, Request $request): Collection
    {
        return $training->expectedPlayers()
            ->filter(fn (Player $speler) => $request->user()->can('createReport', $speler))
            ->values();
    }

    /** @return array{attendance: Collection, ratings: Collection, done: Collection} */
    protected function standen(Training $training): array
    {
        return [
            'attendance' => $training->attendances()->get()->keyBy('player_id'),
            'ratings' => EffortRating::query()->where('training_id', $training->id)->get()->keyBy('player_id'),
            'done' => EffortRating::doneFor($training),
        ];
    }

    /** De volgende die nog open staat, ná deze; daarna vanaf het begin. */
    protected function volgende(Training $training, Request $request, Player $huidige): ?int
    {
        $spelers = $this->spelers($training, $request);
        $gedaan = EffortRating::doneFor($training);

        $positie = $spelers->search(fn (Player $speler) => $speler->id === $huidige->id);
        $vanaf = $positie === false ? 0 : $positie + 1;

        return $spelers->slice($vanaf)->concat($spelers->slice(0, $vanaf))
            ->first(fn (Player $speler) => ! $gedaan->has($speler->id))?->id;
    }
}
