<?php

namespace App\Enums;

/**
 * Hoe een aanbod betaald kan worden. Meerdere per aanbod: een blok van zes
 * weken kan €120 ineens zijn, drie termijnen van €40, of €30 per maand.
 */
enum PaymentOptionType: string
{
    case Eenmalig = 'eenmalig';
    case Termijnen = 'termijnen';
    case Abonnement = 'abonnement';

    public function label(): string
    {
        return match ($this) {
            self::Eenmalig => 'Eenmalig',
            self::Termijnen => 'In termijnen',
            self::Abonnement => 'Per periode (abonnement)',
        };
    }

    /** Brengt dit telkens een nieuwe rekening voort? */
    public function isRecurring(): bool
    {
        return $this === self::Abonnement;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
