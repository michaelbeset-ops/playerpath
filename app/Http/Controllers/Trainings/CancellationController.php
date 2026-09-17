<?php

namespace App\Http\Controllers\Trainings;

use App\Actions\Communication\SendAnnouncement;
use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Een training afzeggen, en iedereen die het aangaat meteen op de hoogte.
 *
 * Dit is waar het hele communicatie-onderdeel om draait: een trainer kijkt om
 * zeven uur naar een ondergelopen veld en elke ouder van die groep weet het
 * binnen een minuut. Afzeggen zonder bericht zou de helft van de waarde weghalen.
 *
 * De training wordt niet verwijderd. Hij blijft in het rooster staan als
 * afgezegd, want "er stond een training die niet doorging" is iets anders dan
 * "er stond niets", en de aanwezigheid van die dag hoort niet als afwezigheid
 * te gaan tellen.
 */
class CancellationController extends Controller
{
    public function __construct(protected SendAnnouncement $verstuur) {}

    public function store(Request $request, Training $training): RedirectResponse
    {
        $this->authorize('update', $training);

        if ($training->cancelled_at !== null) {
            return back()->with('status', 'Deze training was al afgezegd.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ], [], ['reason' => 'De reden']);

        $training->forceFill([
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['reason'],
        ])->save();

        $bericht = $this->verstuur->handle(
            $request->user(),
            'Training van '.$training->starts_at->format('d-m-Y').' gaat niet door',
            "De training van {$training->starts_at->translatedFormat('l j F')} om {$training->starts_at->format('H:i')} gaat niet door.\n\n{$validated['reason']}",
            $training->group,
            $training,
        );

        return back()->with('status', "De training is afgezegd en {$bericht->recipients_count} ontvanger(s) zijn op de hoogte.");
    }

    public function destroy(Request $request, Training $training): RedirectResponse
    {
        $this->authorize('update', $training);

        $training->forceFill(['cancelled_at' => null, 'cancellation_reason' => null])->save();

        // Bewust géén automatisch bericht: "toch weer aan" is een mededeling
        // die de trainer zelf wil formuleren, met wat er precies verandert.
        return back()->with('status', 'De training staat weer in het rooster. Stuur zelf even een bericht als de groep het al wist.');
    }
}
