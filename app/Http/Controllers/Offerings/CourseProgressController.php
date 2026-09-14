<?php

namespace App\Http\Controllers\Offerings;

use App\Http\Controllers\Controller;
use App\Models\CourseAssessment;
use App\Models\Player;
use App\Models\Product;
use App\Support\Progress\CourseProgress;
use App\Support\Rating\RatingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Begin- en eindniveau per cursus of blok, in kleuren, met een verslag.
 *
 * Voor scholen met de inzetkaart. Kind na kind, net als de invulflow na een
 * training: per categorie een kleur aantikken, eventueel een paar zinnen,
 * opslaan en door naar de volgende. Eigenaar en trainer; een trainer alleen
 * bij zijn eigen spelers (createReport).
 */
class CourseProgressController extends Controller
{
    public function index(Request $request, Product $product): Response
    {
        $this->poort($request, $product);

        $levels = RatingSettings::for($request->user()->school)->progressLevels();
        $deelnemers = $this->deelnemers($request, $product);
        $beoordelingen = CourseAssessment::query()->where('product_id', $product->id)->get()->groupBy('player_id');

        $heeft = fn (int $spelerId, string $moment) => (bool) $beoordelingen->get($spelerId)?->contains('moment', $moment);

        $gevraagd = $request->query('moment');
        $moment = in_array($gevraagd, [CourseAssessment::BEGIN, CourseAssessment::EIND], true) ? $gevraagd : null;
        $speler = $deelnemers->firstWhere('id', $request->integer('speler'));

        // Niets gevraagd: het eerste kind zonder beginniveau, en als iedereen
        // er een heeft het eerste zonder eindniveau.
        if ($speler === null && $deelnemers->isNotEmpty()) {
            $open = $moment ?? ($deelnemers->contains(fn (Player $s) => ! $heeft($s->id, CourseAssessment::BEGIN)) ? CourseAssessment::BEGIN : CourseAssessment::EIND);
            $speler = $deelnemers->first(fn (Player $s) => ! $heeft($s->id, $open)) ?? $deelnemers->first();
            $moment ??= $open;
        }

        if ($speler !== null && $moment === null) {
            $moment = $heeft($speler->id, CourseAssessment::BEGIN) ? CourseAssessment::EIND : CourseAssessment::BEGIN;
        }

        $rij = fn (?string $m) => $speler === null || $m === null ? null : $beoordelingen->get($speler->id)?->firstWhere('moment', $m);
        $huidig = $rij($moment);
        $ander = $rij($moment === CourseAssessment::BEGIN ? CourseAssessment::EIND : CourseAssessment::BEGIN);
        $positie = $speler === null ? false : $deelnemers->search(fn (Player $s) => $s->id === $speler->id);

        return Inertia::render('offerings/CourseProgress', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->label(),
                'starts_on' => $product->starts_on?->format('d-m-Y'),
                'ends_on' => $product->ends_on?->format('d-m-Y'),
            ],
            'canManage' => $request->user()->can('update', $product),
            'levels' => $levels,
            'moment' => $moment ?? CourseAssessment::BEGIN,
            'roster' => $deelnemers->map(fn (Player $s) => [
                'id' => $s->id,
                'name' => $s->full_name,
                'first_name' => $s->first_name,
                'photo' => $s->photo_url,
                'begin' => $heeft($s->id, CourseAssessment::BEGIN),
                'eind' => $heeft($s->id, CourseAssessment::EIND),
            ])->values(),
            'player' => $speler === null ? null : [
                'id' => $speler->id,
                'name' => $speler->full_name,
                'first_name' => $speler->first_name,
                'photo' => $speler->photo_url,
                'position' => $speler->position->label(),
            ],
            'categories' => $speler === null ? [] : array_map(fn ($c) => [
                'category' => $c->value,
                'label' => $c->label(),
                'hint' => $c->hint(),
            ], $speler->position->categories()),
            'current' => $this->vorm($huidig, $levels),
            'other' => $this->vorm($ander, $levels),
            'prev' => $positie !== false && $positie > 0 ? $deelnemers[$positie - 1]->id : null,
            'next' => $positie !== false && $positie + 1 < $deelnemers->count() ? $deelnemers[$positie + 1]->id : null,
        ]);
    }

    public function store(Request $request, Product $product, Player $player): RedirectResponse
    {
        $this->poort($request, $product);
        $this->authorize('create', [CourseAssessment::class, $player]);
        abort_unless($this->deelnemers($request, $product)->contains('id', $player->id), 404);

        $levels = RatingSettings::for($request->user()->school)->progressLevels();
        $categorieen = array_map(fn ($c) => $c->value, $player->position->categories());

        $data = $request->validate([
            'moment' => ['required', Rule::in([CourseAssessment::BEGIN, CourseAssessment::EIND])],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['nullable', 'integer', 'min:0', 'max:'.(count($levels) - 1)],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'moment' => 'Begin of eind',
            'levels' => 'De niveaus',
            'note' => 'Het verslag',
        ]);

        $gekozen = collect($data['levels'] ?? [])
            ->only($categorieen)
            ->reject(fn ($waarde) => $waarde === null)
            ->map(fn ($waarde) => (int) $waarde)
            ->all();

        if ($gekozen === []) {
            return back()->withErrors(['levels' => 'Kies bij minstens één onderdeel een niveau.']);
        }

        $notitie = trim((string) ($data['note'] ?? ''));

        CourseAssessment::query()->updateOrCreate(
            ['product_id' => $product->id, 'player_id' => $player->id, 'moment' => $data['moment']],
            [
                'levels' => $gekozen,
                'scale' => count($levels),
                'note' => $notitie === '' ? null : $notitie,
                'assessed_by' => $request->user()->id,
                'assessed_on' => now()->toDateString(),
            ],
        );

        // Door naar het volgende kind zonder dit moment, eerst vooruit en dan vanaf het begin.
        $deelnemers = $this->deelnemers($request, $product);
        $klaar = CourseAssessment::query()->where('product_id', $product->id)->where('moment', $data['moment'])->pluck('player_id')->flip();
        $positie = $deelnemers->search(fn (Player $s) => $s->id === $player->id);
        $vanaf = $positie === false ? 0 : $positie + 1;
        $volgende = $deelnemers->slice($vanaf)->concat($deelnemers->slice(0, $vanaf))->first(fn (Player $s) => ! $klaar->has($s->id));

        if ($volgende === null) {
            return redirect()
                ->route('offerings.progress', ['product' => $product, 'speler' => $player->id, 'moment' => $data['moment']])
                ->with('status', $data['moment'] === CourseAssessment::BEGIN ? 'Iedereen heeft een beginniveau.' : 'Iedereen heeft een eindniveau.');
        }

        return redirect()->route('offerings.progress', ['product' => $product, 'speler' => $volgende->id, 'moment' => $data['moment']]);
    }

    protected function poort(Request $request, Product $product): void
    {
        $user = $request->user();

        abort_unless($user->belongsToSameSchool($product) && ($user->isEigenaar() || $user->isTrainer()), 403);
        abort_unless(RatingSettings::for($user->school)->usesEffort() && CourseProgress::eligible($product), 404);
    }

    /** @return Collection<int, Player> */
    protected function deelnemers(Request $request, Product $product): Collection
    {
        return $product->participations()
            ->confirmed()
            ->with('player')
            ->get()
            ->pluck('player')
            ->filter()
            ->filter(fn (Player $speler) => $request->user()->can('createReport', $speler))
            ->unique('id')
            ->sortBy(fn (Player $speler) => $speler->full_name)
            ->values();
    }

    /**
     * @param  list<array{key: string, label: string, color: string}>  $levels
     * @return array{levels: array<string, int>, note: ?string, date: string}|null
     */
    protected function vorm(?CourseAssessment $beoordeling, array $levels): ?array
    {
        if ($beoordeling === null) {
            return null;
        }

        return [
            'levels' => collect($beoordeling->levels)
                ->map(fn ($index) => CourseProgress::level($index, $beoordeling->scale, $levels)['index'] ?? null)
                ->filter(fn ($index) => $index !== null)
                ->all(),
            'note' => $beoordeling->note,
            'date' => $beoordeling->assessed_on->format('d-m-Y'),
        ];
    }
}
