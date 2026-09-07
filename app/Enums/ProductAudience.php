<?php

namespace App\Enums;

/** Voor wie een aanbod is: iedereen, alleen keepers of alleen veldspelers. */
enum ProductAudience: string
{
    case All = 'all';
    case Keeper = 'keeper';
    case Field = 'field';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Iedereen',
            self::Keeper => 'Alleen keepers',
            self::Field => 'Alleen veldspelers',
        };
    }

    public function fits(PlayerPosition $position): bool
    {
        return match ($this) {
            self::All => true,
            self::Keeper => $position === PlayerPosition::Keeper,
            self::Field => $position === PlayerPosition::Field,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $a) => [$a->value => $a->label()])->all();
    }
}
