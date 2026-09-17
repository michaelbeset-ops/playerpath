<?php

namespace App\Enums;

use App\Support\Status\HasTransitions;

/**
 * De levensloop van een inschrijving.
 *
 * concept → wachtlijst → wacht op goedkeuring → wacht op betaling → bevestigd
 * → actief → opzegging gepland → beëindigd, of onderweg geannuleerd, afgewezen
 * of verlopen. "Betaling mislukt" is een zijstap waar je uit terugkomt.
 *
 * Wat "actief" onderscheidt van "bevestigd": bevestigd is rond (er is betaald
 * of de school heeft goedgekeurd), actief is dat de trainingen lopen.
 */
enum EnrollmentStatus: string
{
    use HasTransitions;

    case Concept = 'concept';
    case Waitlist = 'waitlist';
    case AwaitingApproval = 'awaiting_approval';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentFailed = 'payment_failed';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case CancellationPlanned = 'cancellation_planned';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
    case Declined = 'declined';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Waitlist => 'Wachtlijst',
            self::AwaitingApproval => 'Wacht op goedkeuring',
            self::AwaitingPayment => 'Wacht op betaling',
            self::PaymentFailed => 'Betaling mislukt',
            self::Confirmed => 'Bevestigd',
            self::Active => 'Actief',
            self::CancellationPlanned => 'Opzegging gepland',
            self::Ended => 'Beëindigd',
            self::Cancelled => 'Geannuleerd',
            self::Declined => 'Afgewezen',
            self::Expired => 'Verlopen',
        };
    }

    /** @return array<string, list<string>> */
    public static function transitions(): array
    {
        return [
            'concept' => ['waitlist', 'awaiting_approval', 'awaiting_payment', 'confirmed', 'cancelled', 'expired'],
            'waitlist' => ['awaiting_approval', 'awaiting_payment', 'confirmed', 'cancelled', 'declined', 'expired'],
            'awaiting_approval' => ['awaiting_payment', 'confirmed', 'waitlist', 'declined', 'cancelled', 'expired'],
            'awaiting_payment' => ['confirmed', 'payment_failed', 'waitlist', 'cancelled', 'expired'],
            'payment_failed' => ['awaiting_payment', 'confirmed', 'waitlist', 'cancelled', 'expired'],
            'confirmed' => ['active', 'cancellation_planned', 'cancelled', 'ended'],
            'active' => ['cancellation_planned', 'ended', 'cancelled'],
            'cancellation_planned' => ['ended', 'active'],
            'ended' => [],
            'cancelled' => [],
            'declined' => [],
            'expired' => ['awaiting_payment', 'waitlist'],
        ];
    }

    /** Ligt dit nog bij de school of de ouder, of is het rond? */
    public function isOpen(): bool
    {
        return in_array($this, [self::Concept, self::Waitlist, self::AwaitingApproval, self::AwaitingPayment, self::PaymentFailed], strict: true);
    }

    /**
     * Houdt deze inschrijving een plek vast?
     *
     * Wie wacht op goedkeuring of betaling heeft nog geen bevestigde deelname,
     * maar telt wel mee als bezet: anders ziet de volgende ouder een plek die
     * al beloofd is. Een concept telt ook, want bij het indienen staan de
     * kinderen van één order even op concept voordat ze verder gaan.
     */
    public function holdsSpot(): bool
    {
        return in_array($this, [self::Concept, self::AwaitingApproval, self::AwaitingPayment, self::PaymentFailed], strict: true);
    }

    /** @return list<string> */
    public static function holdingSpot(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->holdsSpot()),
        ));
    }

    /** Doet het kind (straks) mee? */
    public function isSettled(): bool
    {
        return in_array($this, [self::Confirmed, self::Active, self::CancellationPlanned], strict: true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
