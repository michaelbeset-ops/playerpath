<?php

namespace App\Enums;

/**
 * Staat dit aanbod open voor inschrijving?
 *
 * "Vol" staat hier bewust niet bij. Vol is geen keuze van de school maar een
 * uitkomst van tellen: capaciteit min bevestigde deelnemers. Zou het een status
 * zijn, dan blijft hij op "vol" staan zodra iemand afzegt en weigert de school
 * een plek die er wel is.
 */
enum OfferingStatus: string
{
    case Concept = 'concept';
    case Open = 'open';
    case Gesloten = 'gesloten';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Open => 'Open voor inschrijving',
            self::Gesloten => 'Gesloten',
        };
    }

    /** Mag er op dit moment iemand bij? */
    public function acceptsSignups(): bool
    {
        return $this === self::Open;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
