<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Support\PlayerCard\BadgeSettings;
use App\Support\PlayerCard\PlayerBadges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Welke mijlpalen er bij deze school gelden: voor alle spelers, en waar
 * gewenst per leeftijdscategorie. Voor de eigenaar en de trainer: die
 * bepalen samen wat motiveert.
 */
class BadgeSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $instellingen = BadgeSettings::for($request->user()->school);

        return Inertia::render('badges/Edit', [
            'catalogue' => PlayerBadges::catalogue(),
            'defaultKeys' => $instellingen->defaultKeys(),
            'categories' => BadgeSettings::categories(),
            'overrides' => (object) $instellingen->categoryOverrides(),
            'standard' => BadgeSettings::STANDAARD,
            'custom' => $instellingen->customBadges(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);

        $bekend = array_column(PlayerBadges::catalogue(), 'key');
        $categorieen = array_column(BadgeSettings::categories(), 'key');

        $validated = $request->validate([
            'default' => ['required', 'array', 'min:1', 'max:9'],
            'default.*' => ['string', Rule::in($bekend)],
            'overrides' => ['nullable', 'array'],
            // Eigen mijlpalen: een naam is verplicht, de rest mag leeg. De
            // sleutel komt van de server en wordt alleen teruggestuurd.
            'custom' => ['nullable', 'array', 'max:20'],
            'custom.*.key' => ['nullable', 'string', 'max:40'],
            'custom.*.label' => ['required', 'string', 'max:40'],
            'custom.*.description' => ['nullable', 'string', 'max:120'],
            ...collect($categorieen)->mapWithKeys(fn ($c) => [
                "overrides.{$c}" => ['nullable', 'array', 'max:9'],
                "overrides.{$c}.*" => ['string', Rule::in($bekend)],
            ])->all(),
        ], [
            'default.required' => 'Kies minstens één mijlpaal.',
            'default.min' => 'Kies minstens één mijlpaal.',
            'custom.*.label.required' => 'Geef de mijlpaal een naam.',
            'custom.*.label.max' => 'Houd de naam onder de 40 tekens.',
            'custom.*.description.max' => 'Houd de omschrijving onder de 120 tekens.',
        ]);

        BadgeSettings::save(
            $request->user()->school,
            array_values(array_unique($validated['default'])),
            collect($validated['overrides'] ?? [])->map(fn ($keys) => $keys === null ? null : array_values(array_unique($keys)))->all(),
            array_values($validated['custom'] ?? []),
        );

        return back()->with('status', 'Mijlpalen opgeslagen. Ze gelden meteen voor alle spelers.');
    }
}
