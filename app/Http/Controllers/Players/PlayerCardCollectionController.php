<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\PlayerCard\PlayerCardPresenter;
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
    ) {}

    public function index(Player $player): Response
    {
        $this->authorize('view', $player);

        $huidig = $this->presenter->for($player);
        $seizoenen = $this->presenter->seasons($player, $huidig);

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
}
