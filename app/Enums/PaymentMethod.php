<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Ideal = 'ideal';
    case DirectDebit = 'directdebit';
    case Transfer = 'transfer';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Ideal => 'iDEAL',
            self::DirectDebit => 'SEPA-incasso',
            self::Transfer => 'Overboeking',
            self::Cash => 'Contant',
        };
    }

    /**
     * Kan hier automatisch op geïncasseerd worden?
     *
     * Contant en overboeking niet: dat geld komt buiten het systeem om binnen
     * en zet de eigenaar zelf op betaald. Alleen incasso loopt vanzelf.
     */
    public function isAutomatic(): bool
    {
        return $this === self::DirectDebit;
    }

    /** Wordt dit bedrag bij de school zelf afgerekend? */
    public function isOffline(): bool
    {
        return in_array($this, [self::Transfer, self::Cash], strict: true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
