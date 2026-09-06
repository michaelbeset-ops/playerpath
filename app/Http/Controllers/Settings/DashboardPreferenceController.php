<?php

namespace App\Http\Controllers\Settings;

use App\Enums\DashboardBlock;
use App\Enums\DashboardTile;
use App\Http\Controllers\Controller;
use App\Support\Dashboard\DashboardPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zelf bepalen wat er op je dashboard staat.
 *
 * Alleen voor wie een schooldashboard heeft. Een ouder of speler ziet de kaart
 * van zijn eigen kind; daar valt niets te kiezen, en een leeg instellingen-
 * scherm aanbieden is erger dan er geen aanbieden.
 */
class DashboardPreferenceController extends Controller
{
    public function __construct(protected DashboardPreferences $preferences) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_if($user->visiblePlayerIds() !== [], 404);

        return Inertia::render('settings/Dashboard', $this->preferences->describe($user));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->visiblePlayerIds() !== [], 404);

        $validated = $request->validate([
            'tiles' => ['array'],
            'tiles.*' => ['boolean'],
            'blocks' => ['array'],
            'blocks.*' => ['boolean'],
        ]);

        // Onbekende sleutels vallen hier al af; DashboardPreferences::save()
        // gooit er daarna nog uit wat deze rol niet mag zien.
        $tiles = array_intersect_key($validated['tiles'] ?? [], array_flip(DashboardTile::values()));
        $blocks = array_intersect_key($validated['blocks'] ?? [], array_flip(DashboardBlock::values()));

        $this->preferences->save($user, $tiles, $blocks);

        return back()->with('status', 'Je dashboard is bijgewerkt.');
    }
}
