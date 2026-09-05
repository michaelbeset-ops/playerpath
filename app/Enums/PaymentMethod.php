<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Ideal = 'ideal';
    case DirectDebit = 'directdebit';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Ideal => 'iDEAL',
            self::DirectDebit => 'SEPA-incasso',
            self::Transfer => 'Overboeking',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
