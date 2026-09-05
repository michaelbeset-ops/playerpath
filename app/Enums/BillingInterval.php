<?php

namespace App\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case Once = 'once';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Per maand',
            self::Quarterly => 'Per kwartaal',
            self::Yearly => 'Per jaar',
            self::Once => 'Eenmalig',
        };
    }

    /** Hoeveel keer per jaar dit interval terugkomt; eenmalig telt niet mee. */
    public function timesPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Quarterly => 4,
            self::Yearly => 1,
            self::Once => 0,
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
