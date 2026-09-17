<?php

namespace App\Enums;

use App\Support\Status\HasTransitions;

/**
 * De levensloop van een betaling.
 *
 * open → in behandeling → betaald, of mislukt / verlopen / geannuleerd, en na
 * betaald nog gestorneerd of (deels) terugbetaald. Mislukt en verlopen zijn
 * geen eindpunt: er komt een nieuwe betaallink en dan staat hij weer open.
 */
enum PaymentStatus: string
{
    use HasTransitions;

    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case ChargedBack = 'charged_back';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Openstaand',
            self::Pending => 'In behandeling',
            self::Paid => 'Betaald',
            self::Failed => 'Mislukt',
            self::Expired => 'Verlopen',
            self::Cancelled => 'Geannuleerd',
            self::ChargedBack => 'Gestorneerd',
            self::Refunded => 'Terugbetaald',
            self::PartiallyRefunded => 'Deels terugbetaald',
        };
    }

    /** @return array<string, list<string>> */
    public static function transitions(): array
    {
        return [
            'open' => ['pending', 'paid', 'failed', 'expired', 'cancelled'],
            'pending' => ['paid', 'failed', 'expired', 'cancelled', 'open'],
            'paid' => ['charged_back', 'refunded', 'partially_refunded'],
            'failed' => ['open', 'pending', 'paid', 'cancelled'],
            'expired' => ['open', 'pending', 'paid', 'cancelled'],
            'cancelled' => ['open'],
            'charged_back' => ['open', 'paid'],
            'refunded' => [],
            'partially_refunded' => ['refunded'],
        ];
    }

    /** Telt deze betaling mee als ontvangen geld? */
    public function countsAsRevenue(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded], strict: true);
    }

    /**
     * Kan hier nu online voor betaald worden?
     *
     * Niet bij een voldane, geannuleerde of terugbetaalde rekening, en niet
     * terwijl er al een betaling loopt (dan zou een tweede checkout dubbel
     * afschrijven).
     */
    public function isPayable(): bool
    {
        return in_array($this, [self::Open, self::Failed, self::Expired, self::ChargedBack], strict: true);
    }

    /**
     * Waar de school een rekening met de hand naartoe mag zetten.
     *
     * De volgende stappen uit de statusmachine, zonder wat alleen van de
     * betaalprovider komt (in behandeling, verlopen, gestorneerd, deels
     * terugbetaald).
     *
     * @return list<self>
     */
    public function manualNext(): array
    {
        $alleenProvider = [self::Pending, self::Expired, self::ChargedBack, self::PartiallyRefunded];

        return array_values(array_filter($this->next(), fn (self $status) => ! in_array($status, $alleenProvider, true)));
    }

    /** Staat er nog iets te betalen? */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Open, self::Pending, self::Failed, self::Expired, self::ChargedBack], strict: true);
    }

    /** Vraagt deze status om actie van de eigenaar? */
    public function needsAttention(): bool
    {
        return in_array($this, [self::Failed, self::ChargedBack, self::Expired], strict: true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
