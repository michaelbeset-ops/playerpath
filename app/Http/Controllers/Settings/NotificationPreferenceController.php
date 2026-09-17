<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Welke mail wil je van de school ontvangen.
 *
 * Alleen mail: meldingen in de app zijn niet uit te zetten. Anders mist iemand
 * een afgelasting en heeft de school geen enkele manier meer om hem te bereiken.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $gebruiker = $request->user();

        return Inertia::render('settings/Notifications', [
            'kinds' => collect(User::notificationKinds())
                ->map(fn (string $label, string $sleutel) => [
                    'key' => $sleutel,
                    'label' => $label,
                    'enabled' => $gebruiker->wantsEmail($sleutel),
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $sleutels = array_keys(User::notificationKinds());

        $validated = $request->validate(
            collect($sleutels)->mapWithKeys(fn (string $s) => ["preferences.{$s}" => ['required', 'boolean']])->all()
        );

        $gebruiker = $request->user();

        // Samenvoegen, niet vervangen: de hoofdschakelaar `mail` (een
        // kind-account zonder mailbox) staat niet op dit scherm en moet blijven.
        $gebruiker->forceFill([
            'notification_preferences' => array_merge(
                (array) ($gebruiker->notification_preferences ?? []),
                collect($sleutels)
                    ->mapWithKeys(fn (string $s) => [$s => (bool) $validated['preferences'][$s]])
                    ->all(),
            ),
        ])->save();

        return back()->with('status', 'Je voorkeuren zijn opgeslagen.');
    }
}
