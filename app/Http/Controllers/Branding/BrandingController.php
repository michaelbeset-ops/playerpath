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
            'school' => [
                'name' => $school->name,
                'slug' => $school->slug,
                'logo' => $school->logo_path !== null ? Storage::url($school->logo_path) : null,
                'brand_color' => $school->brand_color,
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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.image' => 'Kies een afbeelding.',
            'logo.max' => 'Het logo mag hoogstens 1 MB groot zijn.',
        ], [
            'brand_color' => 'De merkkleur',
            'logo' => 'Het logo',
        ]);

        $school = $request->user()->school;
        $kleur = $validated['brand_color'] ?? null;

        if ($kleur !== null && $kleur !== '' && ! BrandColor::isValid($kleur)) {
            throw ValidationException::withMessages([
                'brand_color' => 'Gebruik een kleurcode als #1BB85E.',
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
