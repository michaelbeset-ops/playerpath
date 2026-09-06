<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Support\Dashboard\SchoolDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De automatische verjaardagsfelicitatie.
 *
 * Eén schakelaar en één zin. Bewust geen tekstverwerker met plaatjes: hoe meer
 * er in te stellen valt, hoe groter de kans dat er iets halfaf blijft staan en
 * een kind een lege mail krijgt.
 *
 * Alleen de eigenaar: dit gaat namens de school de deur uit.
 */
class BirthdayGreetingController extends Controller
{
    public function __construct(protected SchoolDashboard $dashboard) {}

    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $school = $request->user()->school;

        return Inertia::render('communication/Birthdays', [
            'enabled' => (bool) $school->birthday_greeting,
            'message' => $school->birthday_message,
            // Laten zien wie er de komende tijd aan de beurt is: dat maakt
            // meteen duidelijk of de instelling ergens toe leidt.
            'upcoming' => $this->dashboard->birthdays(days: 60, limit: 12),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ], [], [
            'enabled' => 'De verjaardagsmail',
            'message' => 'Het bericht',
        ]);

        $request->user()->school->update([
            'birthday_greeting' => $validated['enabled'],
            // Een lege zin is geen zin: dan valt hij terug op de standaardtekst.
            'birthday_message' => trim((string) ($validated['message'] ?? '')) ?: null,
        ]);

        return back()->with('status', $validated['enabled']
            ? 'De verjaardagsmail staat aan. Hij gaat elke ochtend om 08:00 uit.'
            : 'De verjaardagsmail staat uit.');
    }
}
