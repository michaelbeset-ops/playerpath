<?php

namespace App\Support\PlayerCard;

use App\Enums\AttendanceStatus;
use App\Enums\GoalStatus;
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

        $rapporten = $player->reports()->with(['scores', 'trainer'])->orderBy('reported_on')->orderBy('id')->get();

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
                'body' => 'Mooie opkomst — dat zie je terug in je cijfers.',
                'value' => null,
                'delta' => null,
            ];
        }

        foreach ($player->goals()->where('status', GoalStatus::Achieved->value)->get() as $goal) {
            $items[] = [
                'type' => 'mijlpaal',
                'date' => $goal->achieved_at->format('d-m-Y'),
                'sort' => $goal->achieved_at->format('Y-m-d').'-3',
                'title' => 'Doel gehaald: '.$goal->category->label().' naar '.$goal->target_rating,
                'body' => $goal->note,
                'value' => null,
                'delta' => null,
            ];
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

        foreach ($player->xpEvents()->orderBy('occurred_on')->orderBy('id')->get() as $event) {
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
