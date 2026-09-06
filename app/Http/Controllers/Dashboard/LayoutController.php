<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DashboardWidget;
use App\Http\Controllers\Controller;
use App\Support\Dashboard\WidgetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De indeling van je eigen dashboard opslaan.
 *
 * Alles gaat via de server: er is geen indeling die alleen in de browser
 * bestaat. Zou je hem lokaal bewaren, dan staat je dashboard er op je telefoon
 * anders bij dan op je laptop, en dat is precies wat "mijn indeling" niet hoort
 * te betekenen.
 *
 * Wat hier binnenkomt wordt niet vertrouwd: een widget die deze rol niet mag
 * zien valt eruit, en een breedte die niet bestaat valt terug op de standaard.
 * Zo kan een aangepast verzoek nooit een scherm opleveren dat cijfers toont
 * waar iemand niet bij mag.
 */
class LayoutController extends Controller
{
    public function __construct(protected WidgetRegistry $registry) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Een ouder of speler ziet de kaart van zijn kind; daar valt niets in
        // te delen. Geen leeg bewerkscherm aanbieden.
        abort_if($user->visiblePlayerIds() !== [], 404);

        $validated = $request->validate([
            'widgets' => ['present', 'array', 'max:50'],
            'widgets.*.key' => ['required', 'string', Rule::in(DashboardWidget::values())],
            'widgets.*.x' => ['required', 'integer', 'between:0,11'],
            'widgets.*.y' => ['required', 'integer', 'between:0,200'],
            'widgets.*.w' => ['required', 'integer', 'between:1,12'],
        ], [], ['widgets' => 'De indeling']);

        $toegestaan = collect($this->registry->describe($user))->keyBy('key');
        $gezien = [];
        $schoon = [];

        foreach ($validated['widgets'] as $rij) {
            $widget = DashboardWidget::from($rij['key']);

            // Wat deze rol niet mag zien, en wat twee keer voorkomt, valt eruit.
            if (! $toegestaan->has($widget->value) || isset($gezien[$widget->value])) {
                continue;
            }

            $gezien[$widget->value] = true;

            $schoon[] = [
                'key' => $widget->value,
                'x' => $rij['x'],
                'y' => $rij['y'],
                'w' => in_array($rij['w'], $widget->sizes(), true) ? $rij['w'] : $widget->defaultSize(),
            ];
        }

        $user->forceFill(['dashboard_layout' => ['widgets' => $schoon]])->save();

        return back()->with('status', 'Je indeling is opgeslagen.');
    }

    /**
     * Terug naar de standaard.
     *
     * Leeggooien en niet de standaard wegschrijven: wie niets heeft ingesteld
     * krijgt de standaard uit code, en ziet een widget die er later bijkomt
     * dus vanzelf verschijnen.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->visiblePlayerIds() !== [], 404);

        $user->forceFill(['dashboard_layout' => null])->save();

        return back()->with('status', 'De standaardindeling staat terug.');
    }
}
