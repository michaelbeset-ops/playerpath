<?php

namespace App\Support\PlayerCard;

use App\Models\Player;
use App\Support\Rating\AgeCategory;
use App\Support\Rating\RatingEngine;

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
    ) {}

    public function for(Player $player, bool $public = false): array
    {
        $settings = $this->engine->settingsFor($player);
        $categorie = $player->age_category ?? $this->engine->categoryFor($player);

        return [
            'first_name' => $player->first_name,
            // Publiek: alleen de initiaal. De achternaam hoort niet op internet.
            'last_name' => $public ? mb_substr($player->last_name, 0, 1).'.' : $player->last_name,
            'name' => $public ? $player->public_name : $player->full_name,
            'photo' => $player->photo_url,
            'position' => $player->position->label(),
            'position_key' => $player->position->value,
            'age_category' => [
                'key' => $categorie,
                'label' => AgeCategory::describe($categorie),
            ],
            'moved_up' => $this->engine->recentlyMovedUp($player),
            'overall' => $player->overall_rating,
            'categories' => $this->calculator->breakdown($player),
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
            'school' => $public ? null : $player->school?->name,
        ];
    }
}
