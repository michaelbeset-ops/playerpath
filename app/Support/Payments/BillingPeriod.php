<?php

namespace App\Support\Payments;

use App\Enums\BillingInterval;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Welke termijn van een abonnement er op een gegeven dag loopt.
 *
 * Bewust gerekend vanaf de startdatum van het abonnement en niet vanaf de
 * eerste van de maand: wie op de 20e begint, betaalt telkens op de 20e. Dat is
 * makkelijker uit te leggen aan een ouder dan een eerste maand die deels wordt
 * berekend, en het scheelt gedoe met halve maanden.
 *
 * De sleutel van een termijn is de startdatum ervan. Die staat in de
 * omschrijving van de betaling, zodat een tweede factuur voor dezelfde maand
 * herkenbaar is en dus niet ontstaat.
 */
final readonly class BillingPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * De termijn die op $op loopt, of null als het abonnement dan niet loopt.
     *
     * Een eenmalig abonnement heeft precies één termijn: die van de startdag.
     */
    public static function forSubscription(Subscription $subscription, CarbonImmutable $op): ?self
    {
        $start = CarbonImmutable::parse($subscription->starts_on)->startOfDay();
        $op = $op->startOfDay();

        if ($op->lt($start)) {
            return null;
        }

        if ($subscription->ends_on !== null && $op->gt(CarbonImmutable::parse($subscription->ends_on)->startOfDay())) {
            return null;
        }

        $maanden = match ($subscription->interval) {
            BillingInterval::Monthly => 1,
            BillingInterval::Quarterly => 3,
            BillingInterval::Yearly => 12,
            BillingInterval::Once => 0,
        };

        if ($maanden === 0) {
            return new self($start, $start);
        }

        // Hoeveel hele termijnen zijn er verstreken sinds de start.
        $verstreken = intdiv((int) $start->diffInMonths($op), $maanden);

        $periodeStart = $start->addMonths($verstreken * $maanden);

        return new self($periodeStart, $periodeStart->addMonths($maanden)->subDay());
    }

    /** Kort en leesbaar, bijvoorbeeld "september 2026" of "sep 2026 - nov 2026". */
    public function label(): string
    {
        if ($this->start->equalTo($this->end)) {
            return $this->start->translatedFormat('F Y');
        }

        return $this->start->translatedFormat('F Y') === $this->end->translatedFormat('F Y')
            ? $this->start->translatedFormat('F Y')
            : $this->start->translatedFormat('M Y').' - '.$this->end->translatedFormat('M Y');
    }

    /** Het kenmerk waarmee we een dubbele factuur herkennen. */
    public function key(): string
    {
        return $this->start->format('Y-m-d');
    }
}
