<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * De publiek deelbare spelerskaart.
 *
 * Dit is de enige pagina van de app zonder inlog, en het gaat om gegevens van
 * een kind. Daarom is hij bewust smal gehouden:
 *
 * - delen staat standaard UIT en moet per speler aangezet worden;
 * - de link is een willekeurig token, niet het id van de speler;
 * - uitzetten wist het token, dus een gedeelde link is daarna direct dood;
 * - er staat alleen voornaam + initiaal op, met de cijfers. Geen achternaam,
 *   geboortedatum, leeftijd, school, groep, trainersnotities of ouders;
 * - de pagina vraagt zoekmachines om hem niet op te nemen.
 *
 * Wie mag delen: de eigenaar van de school en de ouders van dit kind. Een
 * trainer niet — die beslist niet over de zichtbaarheid van andermans kind.
 */
class SharedCardController extends Controller
{
    public function __construct(
        protected CalculatePlayerCard $calculator,
        protected PlayerBadges $badges,
        protected PlayerProgress $progress,
        protected Tenancy $tenancy,
    ) {}

    /** De publieke pagina. Geen sessie, geen school-context. */
    public function show(string $token): Response
    {
        // Zonder ingelogde gebruiker is er geen actieve school, dus de global
        // scope zou hier alles wegfilteren. Dat is precies de bedoeling van
        // fail-closed; voor deze ene, bewust publieke route zetten we hem uit
        // en zoeken we op het token, dat zelf al de sleutel is.
        $player = $this->tenancy->withoutScope(
            fn () => Player::where('share_token', $token)->first()
        );

        abort_if($player === null, HttpResponse::HTTP_NOT_FOUND);

        // Geen school-context laten staan op een pagina die buiten de app valt.
        $this->tenancy->forget();

        return Inertia::render('players/SharedCard', [
            'player' => [
                // De foto hoort bij de kaart; de achternaam niet. Zie de
                // afspraken over de publieke deel-link in CLAUDE.md.
                'name' => $player->public_name,
                'photo' => $player->photo_url,
                'position' => $player->position->label(),
                'position_key' => $player->position->value,
                'overall_rating' => $player->overall_rating,
            ],
            'categories' => array_map(
                fn (array $categorie) => [
                    'label' => $categorie['label'],
                    'rating' => $categorie['rating'],
                ],
                $this->calculator->breakdown($player)
            ),
            'level' => $this->badges->level($player),
            'badges' => array_values(array_filter(
                $this->badges->for($player, $this->progress),
                fn (array $badge) => $badge['earned'],
            )),
        ]);
    }

    public function store(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('share', $player);

        $player->forceFill([
            'share_token' => Str::random(48),
            'shared_at' => now(),
        ])->save();

        return back()->with('status', 'De kaart is nu te delen via een link.');
    }

    public function destroy(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('share', $player);

        $player->forceFill(['share_token' => null, 'shared_at' => null])->save();

        return back()->with('status', 'De deel-link werkt niet meer.');
    }
}
