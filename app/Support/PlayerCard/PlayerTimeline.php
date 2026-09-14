<?php

namespace App\Support\PlayerCard;

use App\Enums\AttendanceStatus;
use App\Enums\GoalStatus;
use App\Models\CourseAssessment;
use App\Models\EffortRating;
use App\Models\Player;
use App\Models\Report;
use App\Models\XpEvent;
use App\Support\Rating\RatingSettings;

/**
 * De tijdlijn van een speler: wat er de afgelopen tijd gebeurd is.
 *
 * Afgeleid uit rapporten en aanwezigheid, niet apart opgeslagen. Een
 * gebeurtenissen-tabel zou uit de pas kunnen lopen met de werkelijkheid, en
 * zou bij elke verwijderde training of speler opgeruimd moeten worden.
 */
class PlayerTimeline
{
    public function __construct(protected PlayerBadges $badges, protected PlayerProgress $progress) {}

    /**
     * @return list<array{type: string, date: string, sort: string, title: string, body: ?string, value: ?int, delta: ?int}>
     */
    public function for(Player $player, int $limit = 20): array
    {
        $items = [];
        $settings = RatingSettings::for($player->school);
        // Bij de inzetkaart geen rapporten en cijfers in de tijdlijn, wel de
        // uitblinkers na een training en het begin en eind van een cursus.
        $inzet = $settings->usesEffort();

        $rapporten = $inzet ? collect() : $player->reports()->with(['scores', 'trainer'])->orderBy('reported_on')->orderBy('id')->get();

        $vorigGemiddelde = null;

        foreach ($rapporten as $report) {
            $scores = $report->scoresByCategory();
            $gemiddelde = $scores === [] ? null : (int) round(array_sum($scores) / count($scores) * 10);

            $delta = ($gemiddelde !== null && $vorigGemiddelde !== null)
                ? $gemiddelde - $vorigGemiddelde
                : null;

            $items[] = [
                'type' => 'rapport',
                'date' => $report->reported_on->format('d-m-Y'),
                'sort' => $report->reported_on->format('Y-m-d').'-2',
                'title' => 'Nieuw rapport'.($report->trainer ? ' van '.$report->trainer->name : ''),
                'body' => $report->note,
                'value' => $gemiddelde,
                'delta' => $delta,
            ];

            $vorigGemiddelde = $gemiddelde;
        }

        // Aanwezigheid samenvatten per training in plaats van per losse rij:
        // "je was er" is pas nieuws als het een reeks wordt.
        $aanwezig = $player->attendances()
            ->where('status', AttendanceStatus::Present->value)
            ->with('training')
            ->get()
            ->filter(fn ($a) => $a->training !== null)
            ->sortBy(fn ($a) => $a->training->starts_at);

        foreach ([5, 10, 25] as $mijlpaal) {
            if ($aanwezig->count() < $mijlpaal) {
                continue;
            }

            $training = $aanwezig->values()->get($mijlpaal - 1)->training;

            $items[] = [
                'type' => 'mijlpaal',
                'date' => $training->starts_at->format('d-m-Y'),
                'sort' => $training->starts_at->format('Y-m-d').'-1',
                'title' => "{$mijlpaal} trainingen aanwezig",
                'body' => $inzet ? 'Mooie opkomst - elke training levert punten op.' : 'Mooie opkomst - dat zie je terug in je cijfers.',
                'value' => null,
                'delta' => null,
            ];
        }

        foreach ($player->goals()->where('status', GoalStatus::Achieved->value)->get() as $goal) {
            $items[] = [
                'type' => 'mijlpaal',
                'date' => $goal->achieved_at->format('d-m-Y'),
                'sort' => $goal->achieved_at->format('Y-m-d').'-3',
                'title' => 'Doel gehaald: '.$goal->describe(),
                'body' => $goal->note,
                'value' => null,
                'delta' => null,
            ];
        }

        // Eigen mijlpalen die een trainer heeft toegekend: het enige in deze
        // tijdlijn dat wél is opgeslagen, omdat er niets is om het uit af te leiden.
        $eigen = collect(BadgeSettings::for($player->school)->customBadges())->keyBy('key');

        foreach ($player->awardedBadges()->with('awardedBy')->get() as $toekenning) {
            $definitie = $eigen->get($toekenning->badge_key);

            if ($definitie === null) {
                continue;
            }

            $items[] = [
                'type' => 'mijlpaal',
                'date' => $toekenning->awarded_on->format('d-m-Y'),
                'sort' => $toekenning->awarded_on->format('Y-m-d').'-4',
                'title' => 'Mijlpaal: '.$definitie['label'],
                'body' => $toekenning->note ?: ($toekenning->awardedBy ? 'Toegekend door '.$toekenning->awardedBy->name : $definitie['description']),
                'value' => null,
                'delta' => null,
            ];
        }

        if ($inzet) {
            $items = array_merge($items, $this->inzetMomenten($player, $settings), $this->cursusMomenten($player));
        }

        $items = array_merge($items, $this->levelMomenten($player));

        usort($items, fn ($a, $b) => strcmp($b['sort'], $a['sort']));

        return array_slice($items, 0, $limit);
    }

    /**
     * Wanneer deze speler een level omhoog ging.
     *
     * Afgeleid uit de XP-boekhouding: we tellen de boekingen op volgorde op en
     * kijken wanneer de som een drempel passeerde. Zo is een level-up een
     * moment met een datum, zonder dat er iets extra's opgeslagen hoeft te
     * worden dat uit de pas kan lopen met de boekhouding.
     *
     * @return list<array<string, mixed>>
     */
    protected function levelMomenten(Player $player): array
    {
        $drempels = array_values(array_filter(
            RatingSettings::for($player->school)->levels(),
            fn (array $level) => $level['xp'] > 0,
        ));

        if ($drempels === []) {
            return [];
        }

        $items = [];
        $totaal = 0;
        $volgende = 0;

        $uitgesloten = RatingSettings::for($player->school)->excludedXpSources();

        foreach ($player->xpEvents()->whereNotIn('source', $uitgesloten)->orderBy('occurred_on')->orderBy('id')->get() as $event) {
            /** @var XpEvent $event */
            $totaal += $event->points;

            // Een while-lus: één grote boeking kan twee drempels tegelijk passeren.
            while ($volgende < count($drempels) && $totaal >= $drempels[$volgende]['xp']) {
                $items[] = [
                    'type' => 'level',
                    'date' => $event->occurred_on->format('d-m-Y'),
                    // De index erachter: gaat iemand op één dag twee levels
                    // omhoog, dan hoort de hoogste bovenaan te staan.
                    'sort' => $event->occurred_on->format('Y-m-d').'-4'.$volgende,
                    'title' => 'Level omhoog: '.$drempels[$volgende]['label'],
                    'body' => 'Bereikt met '.$drempels[$volgende]['xp'].' XP.',
                    'value' => null,
                    'delta' => null,
                ];

                $volgende++;
            }
        }

        return $items;
    }

    /**
     * De trainingen waarin een kind de hoogste trede van inzet of houding
     * kreeg. Niet elke training: "je was er" is geen nieuws, uitblinken wel.
     *
     * @return list<array<string, mixed>>
     */
    protected function inzetMomenten(Player $player, RatingSettings $settings): array
    {
        $inzet = collect($settings->effortLevels())->last();
        $houding = collect($settings->attitudeLevels())->last();

        return $player->effortRatings()
            ->with('training')
            ->get()
            ->filter(fn (EffortRating $r) => $r->training !== null
                && (($inzet !== null && $r->effort === $inzet['key']) || ($houding !== null && $r->attitude === $houding['key'])))
            ->map(fn (EffortRating $r) => [
                'type' => 'inzet',
                'date' => $r->training->starts_at->format('d-m-Y'),
                'sort' => $r->training->starts_at->format('Y-m-d').'-2',
                'title' => implode(' en ', array_filter([
                    $inzet !== null && $r->effort === $inzet['key'] ? $inzet['label'] : null,
                    $houding !== null && $r->attitude === $houding['key'] ? $houding['label'] : null,
                ])).' bij '.$r->training->label(),
                'body' => $r->note,
                'value' => null,
                'delta' => null,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function cursusMomenten(Player $player): array
    {
        return $player->courseAssessments()
            ->with('product')
            ->get()
            ->filter(fn (CourseAssessment $a) => $a->product !== null)
            ->map(fn (CourseAssessment $a) => [
                'type' => 'cursus',
                'date' => $a->assessed_on->format('d-m-Y'),
                'sort' => $a->assessed_on->format('Y-m-d').'-3',
                'title' => ($a->moment === CourseAssessment::BEGIN ? 'Beginniveau vastgelegd: ' : 'Eindverslag: ').$a->product->name,
                'body' => $a->note ?? ($a->moment === CourseAssessment::BEGIN
                    ? 'De trainer weet nu waar je begint. Aan het eind zie je hoe ver je bent gekomen.'
                    : 'Bekijk bij Voortgang waar je beter in bent geworden.'),
                'value' => null,
                'delta' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * Een korte terugblik over de laatste drie maanden, voor de ouder.
     *
     * @return array{reports: int, trainings: int, growth: ?int, best: ?string}
     */
    public function quarterSummary(Player $player): array
    {
        $vanaf = now()->subMonths(3);

        $rapporten = $player->reports()->where('reported_on', '>=', $vanaf)->with('scores')->orderBy('reported_on')->get();

        $gemiddelden = $rapporten->map(function (Report $report) {
            $scores = $report->scoresByCategory();

            return $scores === [] ? null : (int) round(array_sum($scores) / count($scores) * 10);
        })->filter()->values();

        $ratings = $player->category_ratings ?? [];
        $beste = null;

        if ($ratings !== []) {
            $besteSleutel = array_search(max($ratings), $ratings, strict: true);
            $beste = collect($player->position->categories())
                ->first(fn ($c) => $c->value === $besteSleutel)?->label();
        }

        return [
            'reports' => $rapporten->count(),
            'trainings' => $player->attendances()
                ->where('status', AttendanceStatus::Present->value)
                ->whereHas('training', fn ($q) => $q->where('starts_at', '>=', $vanaf))
                ->count(),
            'growth' => $gemiddelden->count() >= 2 ? $gemiddelden->last() - $gemiddelden->first() : null,
            'best' => $beste,
        ];
    }
}
