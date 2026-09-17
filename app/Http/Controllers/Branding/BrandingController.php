<?php

namespace App\Http\Controllers\Branding;

use App\Http\Controllers\Controller;
use App\Support\Branding\BrandColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De huisstijl van de eigen school: logo en merkkleur.
 *
 * Alleen de eigenaar. Een trainer die het logo omgooit is niet iets waar
 * iemand op zit te wachten.
 */
class BrandingController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;

        return Inertia::render('branding/Edit', [
            // Niet `school`: dat is een gedeelde prop, en een paginaprop met
            // dezelfde naam overschrijft hem (zie CLAUDE.md).
            'schoolInfo' => [
                'name' => $school->name,
                'slug' => $school->slug,
                'logo' => $school->logo_path !== null ? Storage::url($school->logo_path) : null,
                'brand_color' => $school->brand_color,
                // De tekstkleur die de app op deze merkkleur zet, zodat het
                // knopvoorbeeld dezelfde leesbaarheid toont als de echte knop.
                'brand_foreground' => $school->brand_color !== null && BrandColor::isValid($school->brand_color)
                    ? 'hsl('.BrandColor::fromHex($school->brand_color)->adjustedForContrast()->readableForeground().')'
                    : null,
            ],
            // Het adres waarop deze school straks bereikbaar is; leeg als er
            // geen basisdomein is ingesteld, want dan bestaan subdomeinen niet.
            'domain' => config('app.domain'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $validated = $request->validate([
            'brand_color' => ['nullable', 'string', 'max:7'],
            // 1 MB is ruim voor een logo en houdt een ingescande poster buiten.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.image' => 'Kies een afbeelding.',
            // Geen svg: dat is uitvoerbare opmaak op pagina's die we delen.
            'logo.mimes' => 'Het logo moet een PNG, JPG of WebP zijn.',
            'logo.max' => 'Het logo mag hoogstens 1 MB groot zijn.',
        ], [
            'brand_color' => 'De merkkleur',
            'logo' => 'Het logo',
        ]);

        $school = $request->user()->school;
        $kleur = $validated['brand_color'] ?? null;

        if ($kleur !== null && $kleur !== '' && ! BrandColor::isValid($kleur)) {
            throw ValidationException::withMessages([
                'brand_color' => 'Gebruik een kleurcode als #12813D.',
            ]);
        }

        if ($request->boolean('remove_logo') && $school->logo_path !== null) {
            Storage::disk('public')->delete($school->logo_path);
            $school->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            // Het oude bestand meteen opruimen: anders blijft elke poging
            // staan en groeit de schijf vol met logo's die niemand meer ziet.
            if ($school->logo_path !== null) {
                Storage::disk('public')->delete($school->logo_path);
            }

            $school->logo_path = $request->file('logo')->store("schools/{$school->id}", 'public');
        }

        $school->brand_color = $kleur === '' ? null : $kleur;
        $school->save();

        return back()->with('status', 'De huisstijl is opgeslagen.');
    }
}
