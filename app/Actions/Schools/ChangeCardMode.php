<?php

namespace App\Actions\Schools;

use App\Models\School;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\RatingSettings;

/**
 * Wisselen tussen de prestatiekaart en de inzetkaart.
 *
 * Er gaat niets verloren: rapporten, inzetpunten en cursusniveaus blijven
 * allemaal staan. Wat verandert is welke punten meetellen - de prestatiekaart
 * telt aanwezigheid, rapporten en groei, de inzetkaart aanwezigheid en
 * inzetpunten - en daarom wordt de XP van elke speler opnieuw opgeteld. Zo
 * begint bij een wissel naar inzet iedereen gelijk, en is terugwisselen
 * precies de stand van vroeger.
 */
class ChangeCardMode
{
    public function __construct(protected RatingEngine $engine) {}

    /** Geeft terug of de kaart echt van soort veranderde. */
    public function handle(School $school, string $mode): bool
    {
        $mode = $mode === RatingSettings::INZET ? RatingSettings::INZET : RatingSettings::PRESTATIE;
        $was = RatingSettings::for($school)->cardMode();

        // Altijd wegschrijven, ook als het de standaard is: dan weet de wizard
        // dat de school zelf gekozen heeft.
        $school->forceFill([
            'rating_settings' => array_merge($school->rating_settings ?? [], ['card_mode' => $mode]),
        ])->save();

        if ($was === $mode) {
            return false;
        }

        $this->engine->recalculateAll($school->fresh());

        return true;
    }
}
