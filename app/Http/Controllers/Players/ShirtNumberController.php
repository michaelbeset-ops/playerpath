<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Het rugnummer op de kaart.
 *
 * Wie het mag zetten is dezelfde kring als bij de foto (`personalise` in
 * PlayerPolicy): de eigenaar, de ouders van dit kind en het kind zelf. Het
 * is versiering van de eigen kaart, geen administratie - daarom staat het
 * op de kaartpagina en niet alleen in het spelersformulier.
 */
class ShirtNumberController extends Controller
{
    public function update(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('personalise', $player);

        $data = $request->validate([
            'shirt_number' => ['nullable', 'integer', 'between:1,99'],
        ], [
            'shirt_number.between' => 'Kies een rugnummer van 1 tot en met 99.',
            'shirt_number.integer' => 'Kies een rugnummer van 1 tot en met 99.',
        ], [
            'shirt_number' => 'Het rugnummer',
        ]);

        $nummer = $data['shirt_number'] ?? null;
        $player->forceFill(['shirt_number' => $nummer === null || $nummer === '' ? null : (int) $nummer])->save();

        return back()->with('status', $player->shirt_number === null
            ? 'Het rugnummer is weggehaald.'
            : "Rugnummer {$player->shirt_number} staat op de kaart.");
    }
}
