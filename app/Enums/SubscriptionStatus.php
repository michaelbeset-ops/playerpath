<?php

namespace App\Enums;

use App\Support\Status\HasTransitions;

/**
 * De levensloop van een abonnement: actief → betaling mislukt → gepauzeerd →
 * opzegging gepland → beëindigd. "Opgezegd" (cancelled) is het oude woord voor
 * beëindigd en blijft bestaan voor wat er al staat.
 */
enum SubscriptionStatus: string
{
    use HasTransitions;

    case Active = 'active';
    case PaymentFailed = 'payment_failed';
    case Paused = 'paused';
    case CancellationPlanned = 'cancellation_planned';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actief',
            self::PaymentFailed => 'Betaling mislukt',
            self::Paused => 'Gepauzeerd',
            self::CancellationPlanned => 'Opzegging gepland',
            self::Ended => 'Beëindigd',
            self::Cancelled => 'Opgezegd',
        };
    }

    /** @return array<string, list<string>> */
    public static function transitions(): array
    {
        return [
            'active' => ['payment_failed', 'paused', 'cancellation_planned', 'ended', 'cancelled'],
            'payment_failed' => ['active', 'paused', 'cancellation_planned', 'ended', 'cancelled'],
            'paused' => ['active', 'cancellation_planned', 'ended', 'cancelled'],
            'cancellation_planned' => ['active', 'ended', 'cancelled'],
            'ended' => [],
            'cancelled' => [],
        ];
    }

    /** Brengt dit abonnement nog rekeningen voort? */
    public function bills(): bool
    {
        return in_array($this, [self::Active, self::PaymentFailed, self::CancellationPlanned], strict: true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
