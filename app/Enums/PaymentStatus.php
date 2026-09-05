<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case ChargedBack = 'charged_back';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Openstaand',
            self::Paid => 'Betaald',
            self::Failed => 'Mislukt',
            self::Refunded => 'Terugbetaald',
            self::ChargedBack => 'Gestorneerd',
        };
    }

    /** Telt deze betaling mee als ontvangen geld? */
    public function countsAsRevenue(): bool
    {
        return $this === self::Paid;
    }

    /** Vraagt deze status om actie van de eigenaar? */
    public function needsAttention(): bool
    {
        return in_array($this, [self::Failed, self::ChargedBack], strict: true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
