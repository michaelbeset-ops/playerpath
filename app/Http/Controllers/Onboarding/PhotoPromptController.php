<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De foto, meteen na het activeren van een ouder- of speleraccount.
 *
 * Zonder foto is de spelerskaart voor een kind de helft minder waard, en het
 * moment waarop iemand zijn account net heeft aangemaakt is het moment waarop
 * hij er tijd voor heeft. Daarom staat dit scherm vóór het dashboard: één
 * keer, met de kinderen (of jezelf) zonder foto, en een knop om het later te
 * doen. Overslaan mag altijd - een verplichte foto is precies waar iemand
 * afhaakt - maar dan blijft er een herinnering staan op het dashboard en op de
 * kaart, totdat de foto er is.
 */
class PhotoPromptController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $spelers = match (true) {
            $user->isOuder() => $user->children()->active()->orderBy('first_name')->get(),
            $user->isSpeler() && $user->player !== null => collect([$user->player]),
            default => collect(),
        };

        $zonderFoto = $spelers->filter(fn (Player $speler) => $speler->photo_path === null)->values();

        // Niets meer te doen: dan hoort dit scherm er niet te zijn. De melding
        // van een zojuist gezette foto reist mee naar het dashboard.
        if ($zonderFoto->isEmpty()) {
            return redirect()->route('dashboard')->with('status', session('status'));
        }

        return Inertia::render('onboarding/PhotoPrompt', [
            'players' => $zonderFoto->map(fn (Player $speler) => [
                'id' => $speler->id,
                'name' => $speler->full_name,
                'first_name' => $speler->first_name,
                'photo' => $speler->photo_url,
            ]),
            // Het kind zelf krijgt "jouw kaart", een ouder "de kaart van Sem".
            'self' => $user->isSpeler(),
        ]);
    }
}
