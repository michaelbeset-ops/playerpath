<?php

namespace App\Support\Reports;

use App\Models\Player;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Rating\RatingEngine;

/**
 * Wat er door een rapport veranderde aan de kaart.
 *
 * Een trainer die op opslaan drukt hoort te zien wát hij heeft aangericht:
 * welke categorie omhoog ging, wat de kaart nu staat, hoeveel XP het opleverde
 * en of er een level bij kwam. Zonder dat is opslaan een formulier dat sluit.
 *
 * De cijfers komen uit dezelfde bron als de kaart zelf — `breakdown()` en dus
 * `CalculatePlayerCard::afronden()`. Zou dit apart rekenen, dan zou de viering
 * "+3" zeggen waar de kaart "+2" laat zien, en dan gelooft niemand het meer.
 *
 * Twee losse stappen, geen toestand: `snapshot()` vóór het opslaan,
 * `changes()` erna. Zo hoeft de opslag-actie hier niets van te weten.
 */
class ReportOutcome
{
    public function __construct(
        protected CalculatePlayerCard $calculator,
        protected PlayerBadges $badges,
        protected PlayerProgress $progress,
        protected RatingEngine $engine,
    ) {}

    /**
     * De stand vóór het rapport.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Player $player): array
    {
        return [
            'overall' => $player->overall_rating,
            'categories' => $this->cijfers($player),
            'xp' => (int) $player->xp,
            'level' => $this->engine->levelState($player),
            'badges' => $this->behaaldeBadges($player),
        ];
    }

    /**
     * Het verschil met die stand, klaar voor het scherm.
     *
     * @param  array<string, mixed>  $voor
     * @return array<string, mixed>
     */
    public function changes(array $voor, Player $player): array
    {
        $na = $this->snapshot($player);

        $categorieen = [];

        foreach ($this->calculator->breakdown($player) as $categorie) {
            $vanaf = $voor['categories'][$categorie['category']] ?? null;
            $tot = $categorie['rating'];

            // Een categorie die er nog niet was telt niet als groei: van niets
            // naar 70 is geen sprong van zeventig punten.
            if ($vanaf === null || $tot === null || $vanaf === $tot) {
                continue;
            }

            $categorieen[] = [
                'category' => $categorie['category'],
                'label' => $categorie['label'],
                'from' => $vanaf,
                'to' => $tot,
                'delta' => $tot - $vanaf,
            ];
        }

        // De grootste verandering eerst: dat is waar de trainer naar kijkt.
        usort($categorieen, fn (array $a, array $b) => abs($b['delta']) <=> abs($a['delta']));

        return [
            'player' => [
                'id' => $player->id,
                'first_name' => $player->first_name,
            ],
            'overall' => [
                'from' => $voor['overall'],
                'to' => $na['overall'],
                'delta' => ($voor['overall'] === null || $na['overall'] === null)
                    ? null
                    : $na['overall'] - $voor['overall'],
            ],
            'categories' => $categorieen,
            'xp' => [
                'from' => $voor['xp'],
                'to' => $na['xp'],
                'gained' => $na['xp'] - $voor['xp'],
            ],
            'level' => [
                'from' => ['key' => $voor['level']['key'], 'label' => $voor['level']['label']],
                'to' => ['key' => $na['level']['key'], 'label' => $na['level']['label']],
                // Alleen omhoog is een viering waard; zakken gebeurt hooguit
                // door een correctie en verdient geen confetti.
                'up' => $voor['level']['key'] !== $na['level']['key']
                    && $na['level']['xp'] >= $voor['level']['xp'],
            ],
            'badges' => $this->nieuweBadges($voor['badges'], $player),
        ];
    }

    /**
     * De kaartcijfers per categorie, afgerond zoals de kaart ze toont.
     *
     * @return array<string, int|null>
     */
    protected function cijfers(Player $player): array
    {
        $cijfers = [];

        foreach ($this->calculator->breakdown($player) as $categorie) {
            $cijfers[$categorie['category']] = $categorie['rating'];
        }

        return $cijfers;
    }

    /** @return list<string> */
    protected function behaaldeBadges(Player $player): array
    {
        return array_values(array_map(
            fn (array $badge) => $badge['key'],
            array_filter($this->badges->for($player, $this->progress), fn (array $badge) => $badge['earned']),
        ));
    }

    /**
     * De mijlpalen die er dóór dit rapport bij kwamen.
     *
     * @param  list<string>  $voor
     * @return list<array{key: string, label: string, description: string}>
     */
    protected function nieuweBadges(array $voor, Player $player): array
    {
        return array_values(array_map(
            fn (array $badge) => [
                'key' => $badge['key'],
                'label' => $badge['label'],
                'description' => $badge['description'],
            ],
            array_filter(
                $this->badges->for($player, $this->progress),
                fn (array $badge) => $badge['earned'] && ! in_array($badge['key'], $voor, true),
            ),
        ));
    }
}
