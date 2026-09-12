<?php

namespace App\Http\Controllers\Players;

use App\Enums\ReportCategory;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\PlayerCard\PlayerCardPresenter;
use App\Support\Rating\AgeCategory;
use App\Support\Rating\RatingEngine;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mijn kaarten: de kaart van nu en de kaarten van vorige seizoenen.
 *
 * Aan het eind van elke jaargang bewaart `players:categories` de kaart als
 * seizoenskaart (`player_card_seasons`). Hier staan ze op een rij, als een
 * verzameling: een kind ziet waar het vandaan komt, en dat is precies het
 * gevoel van sparen dat een verzamelkaart geeft. De oude kaarten worden met
 * dezelfde component getekend als de kaart van nu, uit de bewaarde cijfers.
 */
class PlayerCardCollectionController extends Controller
{
    public function __construct(
        protected PlayerCardPresenter $presenter,
        protected RatingEngine $engine,
    ) {}

    public function index(Player $player): Response
    {
        $this->authorize('view', $player);

        $huidig = $this->presenter->for($player);
        $levels = $this->engine->settingsFor($player)->levels();

        $seizoenen = $player->cardSeasons()
            ->get()
            ->map(fn (PlayerCardSeason $kaart) => $this->kaart($kaart, $player, $huidig, $levels))
            ->values();

        return Inertia::render('players/Cards', [
            'player' => [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'name' => $player->full_name,
            ],
            'current' => $huidig,
            'seasons' => $seizoenen,
            'isOwn' => auth()->user()->isSpeler() && $player->user_id === auth()->id(),
        ]);
    }

    /**
     * Een seizoenskaart in de vorm van de kaart van nu, uit de bewaarde cijfers.
     *
     * @param  array<string, mixed>  $huidig
     * @param  list<array{key: string, label: string, xp: int}>  $levels
     * @return array<string, mixed>
     */
    protected function kaart(PlayerCardSeason $kaart, Player $player, array $huidig, array $levels): array
    {
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
            // Mijlpalen en de achterkant horen bij nu, niet bij toen.
            'badges' => [],
            'recent_reports' => null,
            'goal' => null,
            'season' => $kaart->season,
            'archived' => true,
        ];
    }
}
