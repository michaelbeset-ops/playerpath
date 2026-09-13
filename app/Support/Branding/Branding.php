<?php

namespace App\Support\Branding;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Welke huisstijl hoort bij dit verzoek.
 *
 * De volgorde is het hele punt van deze klasse:
 *
 * 1. **De ingelogde gebruiker wint altijd.** Zijn school bepaalt wat hij ziet,
 *    ook als hij toevallig op het adres van een andere school binnenkomt. Dan
 *    ziet hij zijn eigen merk in plaats van dat van een vreemde - verwarrend
 *    zou het pas worden als het andersom kon.
 * 2. **Anders het subdomein**, zodat de inlogpagina en het inschrijfformulier
 *    van een school er al uitzien als die school voordat er iemand inlogt.
 * 3. **Anders PlayerPath zelf.**
 *
 * Wat hier nadrukkelijk NIET gebeurt: het subdomein gebruiken om te bepalen
 * welke data iemand mag zien. Dat blijft `SetCurrentSchool` op basis van het
 * account, en dat mag nooit verschuiven - zie CLAUDE.md 3.1.
 */
class Branding
{
    public function forRequest(Request $request): ?School
    {
        // De publiek gedeelde spelerskaart krijgt bewust géén huisstijl. Daar
        // hoort niet te staan bij welke school het kind zit - niet met zoveel
        // woorden, en dus ook niet via een logo. Zie CLAUDE.md over de deel-link.
        if ($request->routeIs('players.shared')) {
            return null;
        }

        // Het openbare inschrijfformulier haalt de school uit de URL; dat is
        // daar de bedoeling, en de naam van de school staat er toch al op.
        if ($request->routeIs('enroll.*')) {
            $uitUrl = $request->route('school');

            return $uitUrl instanceof School ? $uitUrl : null;
        }

        $user = $request->user();

        if ($user?->school !== null) {
            return $user->school;
        }

        return $this->fromHost($request->getHost());
    }

    /**
     * De school die bij dit adres hoort, of null.
     *
     * Zonder ingestelde APP_DOMAIN doen we niets: op een enkel adres (lokaal,
     * of achter een tunnel) is elk subdomein betekenisloos en zou raden alleen
     * maar verkeerde branding opleveren.
     */
    public function fromHost(string $host): ?School
    {
        $domein = config('app.domain');

        if (blank($domein) || $host === $domein || ! str_ends_with($host, '.'.$domein)) {
            return null;
        }

        $slug = substr($host, 0, -(strlen($domein) + 1));

        if ($slug === '' || str_contains($slug, '.')) {
            return null;
        }

        return School::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * De huisstijl als iets wat een view kan gebruiken.
     *
     * @return array<string, mixed>
     */
    public function describe(?School $school): array
    {
        $kleur = $school?->brand_color !== null && BrandColor::isValid($school->brand_color)
            ? BrandColor::fromHex($school->brand_color)
            : null;

        // De kleur die daadwerkelijk in de interface komt: zo nodig een tikje
        // lichter of donkerder, zodat de tekst op een knop leesbaar blijft.
        $bruikbaar = $kleur?->adjustedForContrast();

        return [
            'name' => $school?->name ?? config('app.name'),
            'logo' => $school?->logo_path !== null ? Storage::url($school->logo_path) : null,
            // Wat de school koos, en wat het geworden is. Die twee kunnen
            // verschillen en dat mag ze weten.
            'color' => $school?->brand_color,
            'usedColor' => $bruikbaar?->toHex(),
            'adjusted' => $kleur !== null && $kleur->needsAdjustment(),
            // Leeg laten als er geen eigen kleur is: dan blijft het merkgroen
            // uit app.css staan en hoeven we niets te overschrijven.
            'primary' => $bruikbaar?->toHslTriplet(),
            'primaryForeground' => $bruikbaar?->readableForeground(),
        ];
    }
}
