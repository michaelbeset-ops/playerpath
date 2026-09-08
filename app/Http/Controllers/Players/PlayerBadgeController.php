<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\PlayerCard\BadgeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Een eigen mijlpaal toekennen of intrekken.
 *
 * Wie een rapport over deze speler mag schrijven, mag hem ook een mijlpaal
 * geven: dat is de trainer die hem kent, en de eigenaar. Alleen mijlpalen die
 * de school zelf heeft bedacht; de standaardmijlpalen worden afgeleid en zijn
 * niet met de hand te zetten.
 */
class PlayerBadgeController extends Controller
{
    public function store(Request $request, Player $player, string $badge): RedirectResponse
    {
        $this->authorize('createReport', $player);

        $definitie = $this->definitie($player, $badge);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:160'],
        ]);

        $player->awardedBadges()->firstOrCreate(
            ['badge_key' => $definitie['key']],
            [
                'awarded_by' => $request->user()->id,
                'awarded_on' => now()->toDateString(),
                'note' => $validated['note'] ?? null,
            ],
        );

        return back()->with('status', "Mijlpaal \"{$definitie['label']}\" toegekend aan {$player->first_name}.");
    }

    public function destroy(Player $player, string $badge): RedirectResponse
    {
        $this->authorize('createReport', $player);

        $definitie = $this->definitie($player, $badge);

        $player->awardedBadges()->where('badge_key', $definitie['key'])->delete();

        return back()->with('status', "Mijlpaal \"{$definitie['label']}\" ingetrokken.");
    }

    /**
     * @return array{key: string, label: string, description: string}
     */
    protected function definitie(Player $player, string $badge): array
    {
        $definitie = collect(BadgeSettings::for($player->school)->customBadges())->firstWhere('key', $badge);

        abort_if($definitie === null, 404);

        return $definitie;
    }
}
