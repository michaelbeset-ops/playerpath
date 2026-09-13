<?php

namespace App\Support\PlayerCard;

use App\Enums\ReportCategory;
use App\Models\Goal;
use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Models\Report;
use App\Support\Goals\GoalProgress;
use App\Support\Rating\AgeCategory;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\SchoolSeason;
use Illuminate\Support\Facades\Storage;

/**
 * Alles wat de spelerskaart nodig heeft, in één pakket.
 *
 * De kaart staat op drie plekken (kaartpagina, dashboard van het gezin en de
 * publieke deel-link) en hoort er overal hetzelfde uit te zien. Eén presenter
 * in plaats van drie keer dezelfde array in drie controllers: dan kan de
 * kaart niet uit elkaar lopen.
 *
 * `public` is de publieke variant: alleen voornaam + initiaal, geen school.
 * Zie de afspraken over de deel-link in CLAUDE.md.
 *
 * @return array<string, mixed>
 */
class PlayerCardPresenter
{
    public function __construct(
        protected CalculatePlayerCard $calculator,
        protected PlayerBadges $badges,
        protected PlayerProgress $progress,
        protected RatingEngine $engine,
        protected GoalProgress $goals,
    ) {}

    public function for(Player $player, bool $public = false): array
    {
        $settings = $this->engine->settingsFor($player);
        $categorie = $player->age_category ?? $this->engine->categoryFor($player);
        $seizoen = SchoolSeason::for($player->school);

        return [
            'first_name' => $player->first_name,
            // Publiek: alleen de initiaal. De achternaam hoort niet op internet.
            'last_name' => $public ? mb_substr($player->last_name, 0, 1).'.' : $player->last_name,
            'name' => $public ? $player->public_name : $player->full_name,
            'photo' => $player->photo_url,
            'position' => $player->position->label(),
            'position_key' => $player->position->value,
            // Het rugnummer, zoals op een shirt; en een kaartnummer als bij
            // een verzamelkaart - vast per speler, dus herkenbaar op elke
            // seizoenskaart.
            'shirt_number' => $player->shirt_number,
            'card_number' => sprintf('#%04d', $player->id),
            'age_category' => [
                'key' => $categorie,
                'label' => AgeCategory::describe($categorie),
            ],
            'moved_up' => $this->engine->recentlyMovedUp($player),
            'overall' => $player->overall_rating,
            // Per categorie ook wat het laatste rapport veranderde: een pijltje
            // op de kaart maakt groei voelbaar in plaats van alleen een stand.
            'categories' => $this->metDeltas($player),
            'report_count' => $player->reports()->count(),
            'level' => $this->badges->level($player),
            'levels' => array_map(
                fn (array $level) => ['key' => $level['key'], 'label' => $level['label'], 'xp' => $level['xp']],
                $settings->levels(),
            ),
            'badges' => array_values(array_filter(
                $this->badges->for($player, $this->progress),
                fn (array $badge) => $badge['earned'],
            )),
            'season' => AgeCategory::seasonLabel(now(), $settings->seasonStartMonth()),
            // Het seizoen van de school: de naam op de kaart, wanneer het
            // eindigt en in welke week we zitten. Voor de balk en de uitleg.
            'season_label' => $seizoen->isSet() ? $seizoen->label() : 'Seizoen '.AgeCategory::seasonLabel(now(), $settings->seasonStartMonth()),
            'season_ends' => $seizoen->isSet() ? $seizoen->endsOn?->format('d-m-Y') : null,
            'season_week' => $seizoen->currentWeek() !== null ? 'week '.$seizoen->currentWeek().' van '.$seizoen->totalWeeks() : null,
            'school' => $public ? null : $player->school?->name,
            // Het logo van de school, voor op de deel-afbeelding. Publiek niet:
            // een logo verraadt net zo goed als een naam waar het kind zit.
            'school_logo' => $public || $player->school?->logo_path === null ? null : Storage::url($player->school->logo_path),
            // De achterkant van de kaart: de laatste rapporten en het doel.
            // Publiek zonder trainer en toelichting, en zonder doel: dat is
            // de opmerking van een trainer over een kind, niet voor internet.
            'recent_reports' => $this->recenteRapporten($player, $public),
            'goal' => $public ? null : $this->doel($player),
        ];
    }

    /**
     * De bewaarde seizoenskaarten, in de vorm van de kaart van nu.
     *
     * Voor Mijn kaarten en de kind-link. Elke oude kaart wordt met dezelfde
     * component getekend, uit de cijfers en het level van toen; mijlpalen en
     * de achterkant horen bij nu en staan er dus niet op.
     *
     * @param  array<string, mixed>  $huidig
     * @return list<array<string, mixed>>
     */
    public function seasons(Player $player, array $huidig): array
    {
        $levels = $this->engine->settingsFor($player)->levels();

        return $player->cardSeasons()
            ->get()
            ->map(function (PlayerCardSeason $kaart) use ($player, $huidig, $levels) {
                $ratings = $kaart->category_ratings ?? [];
                $level = collect($levels)->firstWhere('key', $kaart->level) ?? ['key' => $kaart->level, 'label' => ucfirst((string) $kaart->level), 'xp' => 0];

                return [
                    ...$huidig,
                    'age_category' => ['key' => $kaart->age_category, 'label' => AgeCategory::describe($kaart->age_category)],
                    'moved_up' => false,
                    'overall' => $kaart->overall_rating,
                    'categories' => array_map(fn (ReportCategory $c) => [
                        'category' => $c->value,
                        'label' => $c->label(),
                        'hint' => $c->hint(),
                        'rating' => CalculatePlayerCard::afronden($ratings[$c->value] ?? null),
                        'delta' => null,
                    ], $player->position->categories()),
                    'report_count' => $kaart->report_count,
                    'level' => [
                        'key' => $level['key'],
                        'label' => $level['label'],
                        'xp' => $kaart->xp,
                        'next' => null,
                        'progress' => 100,
                    ],
                    'badges' => [],
                    'recent_reports' => null,
                    'goal' => null,
                    'season' => $kaart->season,
                    'archived' => true,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function metDeltas(Player $player): array
    {
        $deltas = $this->calculator->deltas($player);

        return array_map(
            fn (array $c) => [...$c, 'delta' => $deltas[$c['category']] ?? null],
            $this->calculator->breakdown($player),
        );
    }

    /** @return list<array<string, mixed>> */
    protected function recenteRapporten(Player $player, bool $public): array
    {
        return $player->reports()
            ->newestFirst()
            ->with(['scores', 'trainer'])
            ->limit(3)
            ->get()
            ->map(function (Report $report) use ($public) {
                $scores = $report->scoresByCategory();

                return [
                    'date' => $report->reported_on->format('d-m-Y'),
                    'overall' => $scores === [] ? null : CalculatePlayerCard::afronden(array_sum($scores) / count($scores) * 10),
                    'trainer' => $public ? null : $report->trainer?->name,
                    'note' => $public ? null : $report->note,
                ];
            })
            ->values()
            ->all();
    }

    /** Het eerstvolgende lopende doel, of null. @return array<string, mixed>|null */
    protected function doel(Player $player): ?array
    {
        $doel = $player->goals()->active()->orderBy('due_on')->first();

        if (! $doel instanceof Goal) {
            return null;
        }

        $beeld = $this->goals->describe($doel, $player);

        return [
            'label' => $beeld['label'],
            'target_grade' => $beeld['target_grade'],
            'current_grade' => $beeld['current_grade'],
            'progress' => $beeld['progress'],
            'track_label' => $beeld['track_label'],
            'due' => $beeld['due_on'] ?? null,
        ];
    }
}
