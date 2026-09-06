<?php

namespace App\Support\Features;

use App\Enums\Feature;
use App\Models\School;
use App\Support\Tenancy\Tenancy;

/**
 * Welke functies staan aan voor deze school.
 *
 * Eén plek waar dat wordt beantwoord. De middleware, het menu, de geplande
 * taken en de schermen vragen het hier; niemand leest zelf de json-kolom uit,
 * want dan moet elke plek opnieuw bedenken wat een lege waarde betekent.
 *
 * Twee regels:
 *
 * 1. **Onbekend betekent aan.** Een feature die net is toegevoegd staat bij
 *    elke bestaande school aan. Andersom zou betekenen dat scholen zonder het
 *    te weten iets kwijtraken.
 * 2. **Geen school betekent aan.** Buiten een school-context (de publieke
 *    inschrijfpagina heeft zijn eigen school, een commando loopt per school)
 *    is er niets om op te filteren. De school-scope regelt daar de veiligheid,
 *    niet dit.
 */
class Features
{
    public function __construct(protected Tenancy $tenancy) {}

    public function enabled(Feature $feature, ?School $school = null): bool
    {
        $school ??= $this->tenancy->school();

        return self::enabledFor($school, $feature);
    }

    public function disabled(Feature $feature, ?School $school = null): bool
    {
        return ! $this->enabled($feature, $school);
    }

    /** Zonder afhankelijkheid van de actieve school, voor geplande taken. */
    public static function enabledFor(?School $school, Feature $feature): bool
    {
        if ($school === null) {
            return true;
        }

        $stand = $school->features ?? [];

        return (bool) ($stand[$feature->value] ?? $feature->defaultEnabled());
    }

    /**
     * De volledige stand, voor het beheerscherm.
     *
     * @return list<array{key: string, label: string, description: string, enabled: bool}>
     */
    public function describe(School $school): array
    {
        return array_map(fn (Feature $feature) => [
            'key' => $feature->value,
            'label' => $feature->label(),
            'description' => $feature->description(),
            'enabled' => self::enabledFor($school, $feature),
        ], Feature::cases());
    }

    /**
     * De aan/uit-stand als platte lijst, om naar de app te sturen.
     *
     * @return array<string, bool>
     */
    public function map(?School $school = null): array
    {
        $school ??= $this->tenancy->school();

        $uit = [];

        foreach (Feature::cases() as $feature) {
            $uit[$feature->value] = self::enabledFor($school, $feature);
        }

        return $uit;
    }
}
