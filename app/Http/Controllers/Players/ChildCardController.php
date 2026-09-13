<?php

namespace App\Http\Controllers\Players;

use App\Actions\Players\EnsurePlayerAccount;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * De kind-link: het kind komt op zijn eigen account, zonder wachtwoord.
 *
 * Een keeper van acht heeft geen e-mailadres en geen wachtwoord, maar het is
 * wél zijn kaart. De ouder maakt daarom een privélink (of een QR-code) en
 * zet die op de tablet van het kind. De link logt het kind in op zijn eigen
 * speleraccount (`EnsurePlayerAccount`) en onthoudt dat op dat apparaat, dus
 * daarna opent de app gewoon op zijn dashboard: de kaart, de voortgang,
 * Mijn kaarten en de meldingen, precies zoals een speler met eigen inlog.
 *
 * - **De link is de sleutel.** Een token van 48 tekens, per speler, alleen
 *   te maken door de eigenaar of de ouders (`PlayerPolicy::share`, dezelfde
 *   poort als delen). Opnieuw maken geeft een nieuw token en de oude link is
 *   dood; intrekken ook; en een speler op niet-actief zetten sluit hem
 *   vanzelf (zie Player::booted). Hij verloopt niet: "link verlopen" na drie
 *   maanden snapt een kind niet.
 * - **Het account is een gewoon speleraccount**, met alles wat daarbij hoort
 *   en niets meer: `PlayerPolicy` bepaalt wat een speler mag (zijn eigen
 *   kaart, foto en rugnummer), en de school-scope komt uit het account.
 * - **Geen mail naar het kind**: het adres bestaat niet en `wantsEmail` zegt
 *   nee. De ouder krijgt de mail; het kind ziet het in de app.
 */
class ChildCardController extends Controller
{
    public function __construct(
        protected Tenancy $tenancy,
        protected EnsurePlayerAccount $accounts,
    ) {}

    /** De link openen: inloggen als het kind en door naar zijn dashboard. */
    public function show(Request $request, string $token): RedirectResponse
    {
        // Geen ingelogde gebruiker, dus geen actieve school: de global scope
        // zou alles wegfilteren. Het token is zelf al de sleutel.
        $player = $this->tenancy->withoutScope(
            fn () => Player::where('child_token', $token)->where('is_active', true)->with(['school', 'user'])->first()
        );

        abort_if($player === null || ! $player->school?->is_active, HttpResponse::HTTP_NOT_FOUND);

        $this->tenancy->set($player->school);
        $user = $this->accounts->handle($player);

        // Wie al ingelogd was (de ouder die de link test) wordt netjes
        // gewisseld naar het kind; anders zou de ouder op zijn eigen
        // dashboard uitkomen en denken dat de link niets doet.
        if (Auth::id() !== $user->id) {
            Auth::logout();
            Auth::login($user, remember: true);
            $request->session()->regenerate();
        }

        // Meteen de vraag om de app op het beginscherm te zetten: dit is het
        // moment, met de tablet van het kind in de hand. Later komt hij niet
        // meer als het al een app is.
        return redirect()->route('dashboard')->with('kindWelkom', true);
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
            : "{$player->first_name} heeft nu een eigen link naar zijn account.");
    }

    public function destroy(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('share', $player);

        $player->forceFill(['child_token' => null, 'child_link_at' => null])->save();

        return back()->with('status', "De link van {$player->first_name} werkt niet meer.");
    }
}
