<?php

namespace App\Http\Controllers\Schools;

use App\Actions\Seasons\CloseSeason;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Support\Rating\Grade;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\RatingSettings;
use App\Support\Rating\SchoolSeason;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het seizoen van de school: naam, begin, eind en hoeveel weken.
 *
 * Van de eigenaar. Een trainer ziet in welk seizoen we zitten op de kaarten
 * en het dashboard, maar het begin en einde van een blok is een beslissing
 * over de hele school.
 */
class SeasonController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;
        $seizoen = SchoolSeason::for($school);
        $levels = RatingSettings::for($school)->levels();

        return Inertia::render('schools/Season', [
            'season' => [
                'name' => $seizoen->name,
                'starts_on' => $seizoen->startsOn?->toDateString(),
                'ends_on' => $seizoen->endsOn?->toDateString(),
                'weeks' => $seizoen->weeks,
                'closed_at' => $seizoen->closedAt?->format('d-m-Y'),
                'is_set' => $seizoen->isSet(),
                'is_active' => $seizoen->isActive(),
                'has_ended' => $seizoen->hasEnded(),
                'current_week' => $seizoen->currentWeek(),
                'total_weeks' => $seizoen->totalWeeks(),
                'days_left' => $seizoen->daysLeft(),
            ],
            'levels' => array_map(fn (array $l) => ['key' => $l['key'], 'label' => $l['label'], 'xp' => $l['xp']], $levels),
            'players' => Player::query()->active()->count(),
            'archived' => PlayerCardSeason::query()->count(),
            // Een voorstel voor de naam, zodat het veld niet leeg begint.
            'suggestion' => $this->voorstel(),
            'grading' => Grade::mode($school),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'starts_on' => ['required', 'date'],
            'weeks' => ['required', 'integer', 'between:1,52'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], [
            'ends_on.after_or_equal' => 'De einddatum moet na de startdatum liggen.',
            'weeks.between' => 'Kies tussen 1 en 52 weken.',
        ], [
            'name' => 'De naam',
            'starts_on' => 'De startdatum',
            'ends_on' => 'De einddatum',
            'weeks' => 'Het aantal weken',
        ]);

        $school = $request->user()->school;
        $huidig = SchoolSeason::for($school);
        $start = CarbonImmutable::parse($data['starts_on']);

        // Een nieuw seizoen (andere start dan het vorige, of het vorige is
        // dicht): de punten tellen vanaf de startdatum. Alleen de einddatum
        // of de naam aanpassen laat de punten met rust.
        $nieuw = ! $huidig->isSet() || $huidig->startsOn?->toDateString() !== $start->toDateString();

        SchoolSeason::save($school, [
            'name' => $data['name'],
            'starts_on' => $start->toDateString(),
            'ends_on' => CarbonImmutable::parse($data['ends_on'])->toDateString(),
            'weeks' => (int) $data['weeks'],
            'closed_at' => null,
            'xp_from' => $nieuw ? $start->toDateString() : ($huidig->xpFrom?->toDateString() ?? $start->toDateString()),
        ]);

        if ($nieuw) {
            app(RatingEngine::class)->recalculateAll($school->fresh());
        }

        return back()->with('status', $nieuw
            ? "Het seizoen \"{$data['name']}\" staat ingesteld. De punten tellen vanaf {$start->format('d-m-Y')}."
            : 'Het seizoen is bijgewerkt.');
    }

    /**
     * Beoordelen in kleuren of in cijfers.
     *
     * Wisselen verandert geen rapport: onder de motorkap is het altijd een
     * getal (zie Grade). Alleen wat trainers invullen en ouders zien wisselt.
     */
    public function grading(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $data = $request->validate([
            'mode' => ['required', 'in:'.Grade::KLEUREN.','.Grade::CIJFERS],
        ], [], ['mode' => 'De manier van beoordelen']);

        $school = $request->user()->school;
        $school->forceFill([
            'rating_settings' => array_merge($school->rating_settings ?? [], ['grading' => $data['mode']]),
        ])->save();

        return back()->with('status', $data['mode'] === Grade::KLEUREN
            ? 'Er wordt nu beoordeeld in kleuren. Ouders en spelers zien geen cijfers meer.'
            : 'Er wordt nu beoordeeld in cijfers van 1 tot 10.');
    }

    /** Nu afsluiten, zonder op de einddatum te wachten. */
    public function close(Request $request, CloseSeason $sluit): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;

        if (! SchoolSeason::for($school)->isSet()) {
            return back()->withErrors(['season' => 'Er is geen lopend seizoen om af te sluiten.']);
        }

        $kaarten = $sluit->handle($school);

        return back()->with('status', "Het seizoen is afgesloten. {$kaarten} eindkaarten zijn bewaard; de punten beginnen opnieuw.");
    }

    protected function voorstel(): string
    {
        $maand = (int) now()->month;
        $deel = match (true) {
            $maand >= 8 || $maand <= 1 => 'Najaar',
            $maand <= 4 => 'Voorjaar',
            default => 'Zomer',
        };

        return $deel.' '.now()->year;
    }
}
