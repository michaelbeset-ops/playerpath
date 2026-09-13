<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Branding\Branding;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerCardPresenter;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * De kind-link: de kaart voor het kind zelf, zonder inlog.
 *
 * Een keeper van acht heeft geen e-mailadres en geen wachtwoord, maar het is
 * wél zijn kaart. De ouder maakt daarom een privélink (of een QR-code) en
 * zet die op de tablet van het kind. De pagina leest live uit de database,
 * dus na elk rapport klopt hij vanzelf.
 *
 * Het verschil met de publieke deel-link (`SharedCardController`):
 *
 * - de deel-link is voor internet en bewust kaal (voornaam + initiaal, geen
 *   school); deze link is voor het kind en toont de hele kaart: volledige
 *   naam, rugnummer, school, de achterkant, de mijlpalen, de levelbalk en
 *   Mijn kaarten;
 * - wat een trainer over het kind opschreef (de toelichting) staat er níét
 *   op: dat is voor ouder en trainer;
 * - hij verloopt niet. "Link verlopen" na drie maanden snapt een kind niet.
 *   Wel: intrekken door ouder of eigenaar, opnieuw aanmaken geeft een nieuw
 *   token en de oude link is dood, en de speler op niet-actief zetten sluit
 *   hem vanzelf (zie Player::booted).
 *
 * Twee tokens (`share_token` en `child_token`), zodat de ene link aan of uit
 * kan zonder de andere te raken. Wie hem mag maken: dezelfde poort als delen
 * (`PlayerPolicy::share`), de eigenaar en de ouders van dit kind.
 */
class ChildCardController extends Controller
{
    public function __construct(
        protected PlayerCardPresenter $presenter,
        protected PlayerBadges $badges,
        protected PlayerProgress $progress,
        protected Branding $branding,
        protected Tenancy $tenancy,
    ) {}

    /** De pagina van het kind. Geen sessie; de school komt uit het token. */
    public function show(string $token): Response
    {
        $player = $this->vindSpeler($token);

        // De kaart wordt binnen de school van het kind berekend (de
        // rekenkern kijkt naar de instellingen van de school), en daarna
        // laten we geen school-context staan op een pagina buiten de app.
        $this->tenancy->set($player->school);

        $kaart = $this->presenter->for($player, child: true);

        $props = [
            'card' => $kaart,
            'seasons' => $this->presenter->seasons($player, $kaart),
            // Alle mijlpalen, ook de nog niet behaalde: "wat kan ik nog
            // halen" is voor een kind precies het leuke.
            'badges' => $this->badges->for($player, $this->progress),
            'schoolName' => $player->school?->name,
            'schoolLogo' => $kaart['school_logo'],
            'manifestUrl' => route('players.child.manifest', $token),
        ];

        $this->tenancy->forget();

        return Inertia::render('players/ChildCard', $props)
            // Een eigen manifest, zodat "Zet op je beginscherm" de kaart
            // van dít kind opent, met zijn naam eronder.
            ->withViewData([
                'manifest' => $props['manifestUrl'],
                'appTitle' => $player->first_name,
            ]);
    }

    /**
     * Het manifest voor deze link: op het beginscherm heet de app naar het
     * kind en opent hij op zijn kaart, niet op het dashboard.
     */
    public function manifest(string $token): JsonResponse
    {
        $player = $this->vindSpeler($token);
        $huisstijl = $this->branding->describe($player->school);

        return response()->json([
            'name' => 'Kaart van '.$player->first_name,
            'short_name' => str($player->first_name)->limit(12, '')->trim()->toString(),
            'description' => 'De spelerskaart van '.$player->first_name.' bij '.$huisstijl['name'].'.',
            'start_url' => route('players.child', $token, absolute: false),
            'scope' => route('players.child', $token, absolute: false),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => 'nl',
            'dir' => 'ltr',
            'background_color' => '#0D0F12',
            'theme_color' => '#111A2E',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->withHeaders([
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'private, max-age=300',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    /** Aanmaken, of vernieuwen: altijd een nieuw token, de oude link is dood. */
    public function store(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('share', $player);

        $bestond = $player->hasChildLink();

        $player->forceFill([
            'child_token' => Str::random(48),
            'child_link_at' => now(),
        ])->save();

        return back()->with('status', $bestond
            ? "Er is een nieuwe link voor {$player->first_name}. De oude werkt niet meer."
            : "{$player->first_name} heeft nu een eigen link naar de kaart.");
    }

    public function destroy(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('share', $player);

        $player->forceFill(['child_token' => null, 'child_link_at' => null])->save();

        return back()->with('status', "De link van {$player->first_name} werkt niet meer.");
    }

    protected function vindSpeler(string $token): Player
    {
        // Geen ingelogde gebruiker, dus geen actieve school: de global scope
        // zou alles wegfilteren. Het token is zelf al de sleutel.
        $player = $this->tenancy->withoutScope(
            fn () => Player::where('child_token', $token)->where('is_active', true)->with('school')->first()
        );

        abort_if($player === null, HttpResponse::HTTP_NOT_FOUND);

        return $player;
    }
}
