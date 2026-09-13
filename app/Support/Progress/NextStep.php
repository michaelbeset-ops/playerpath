<?php

namespace App\Support\Progress;

use App\Models\Player;
use App\Support\PlayerCard\CalculatePlayerCard;

/**
 * Eén concreet ding om aan te werken.
 *
 * De voortgangspagina laat zien wat er gebeurd is; dit is de enige regel die
 * vooruit kijkt. Bewust **één** ding: een lijstje met zes verbeterpunten leest
 * als kritiek en niemand begint eraan.
 *
 * Loopt er een doel, dan is dat het volgende doel - de trainer heeft er al
 * over nagedacht. Anders wijzen we de laagste categorie aan met een stap van
 * vijf punten: klein genoeg om te halen, groot genoeg om te merken.
 */
class NextStep
{
    /** Hoeveel punten een voorstel vooruit kijkt. */
    public const STAP = 5;

    public function __construct(protected CalculatePlayerCard $calculator) {}

    /**
     * @param  list<array<string, mixed>>  $goals  de uitkomst van GoalProgress::forPlayer()
     * @return array<string, mixed>|null
     */
    public function for(Player $player, array $goals): ?array
    {
        foreach ($goals as $doel) {
            // Een eigen doel heeft geen cijfers; hier gaat het juist over "van
            // X naar Y". Het staat gewoon in de doelenlijst, alleen niet hier.
            if ($doel['status'] === 'active' && $doel['target'] !== null) {
                return [
                    'type' => 'goal',
                    'category' => $doel['category'],
                    'label' => $doel['label'],
                    'from' => $doel['current'],
                    'to' => $doel['target'],
                    'on_track' => $doel['on_track'],
                    'days_left' => $doel['days_left'],
                ];
            }
        }

        return $this->voorstel($player);
    }

    /**
     * De laagste categorie, vijf punten hoger.
     *
     * Zonder cijfers geen voorstel: "werk aan je communicatie" zonder dat er
     * ooit iemand naar gekeken heeft is een oordeel uit het niets.
     *
     * @return array<string, mixed>|null
     */
    protected function voorstel(Player $player): ?array
    {
        $laagste = null;

        foreach ($this->calculator->breakdown($player) as $categorie) {
            if ($categorie['rating'] === null) {
                continue;
            }

            if ($laagste === null || $categorie['rating'] < $laagste['rating']) {
                $laagste = $categorie;
            }
        }

        if ($laagste === null || $laagste['rating'] >= 100) {
            return null;
        }

        return [
            'type' => 'suggestion',
            'category' => $laagste['category'],
            'label' => $laagste['label'],
            'hint' => $laagste['hint'],
            'from' => $laagste['rating'],
            'to' => min(100, $laagste['rating'] + self::STAP),
            'on_track' => null,
            'days_left' => null,
        ];
    }
}
