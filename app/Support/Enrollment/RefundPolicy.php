<?php

namespace App\Support\Enrollment;

use Carbon\CarbonInterface;

/**
 * Het restitutiebeleid van de school, uitgerekend voor één geval.
 *
 * Kosteloos annuleren tot X dagen voor de start; daarna houdt de school Y%
 * in. Alles in centen, het percentage via `intdiv`. Zonder startdatum (een
 * rittenkaart, doorlopende training) geldt de regel "vóór de start" niet en
 * telt het als ná de start: dan houdt de school haar percentage in.
 */
class RefundPolicy
{
    public function __construct(protected EnrollmentSettings $settings) {}

    public static function for(EnrollmentSettings $settings): self
    {
        return new self($settings);
    }

    /** Is annuleren op dit moment nog kosteloos? */
    public function isFree(?CarbonInterface $start, ?CarbonInterface $op = null): bool
    {
        if ($start === null) {
            return false;
        }

        $op ??= now();
        $dagen = (int) $op->copy()->startOfDay()->diffInDays($start->copy()->startOfDay(), false);

        return $dagen >= (int) $this->settings->get('cancellation')['free_until_days'];
    }

    /** Hoeveel er van een betaald bedrag terugkomt. */
    public function refundCents(int $betaald, ?CarbonInterface $start, ?CarbonInterface $op = null): int
    {
        if ($betaald <= 0) {
            return 0;
        }

        if ($this->isFree($start, $op)) {
            return $betaald;
        }

        $inhouden = (int) $this->settings->get('cancellation')['retain_percent'];

        return max(0, $betaald - intdiv($betaald * $inhouden, 100));
    }

    /** De regel in gewone taal, voor op het formulier en in de mail. */
    public function describe(): string
    {
        $regel = $this->settings->get('cancellation');

        return "Kosteloos annuleren tot {$regel['free_until_days']} dagen voor de start; daarna houdt de school {$regel['retain_percent']}% in.";
    }
}
