<?php

namespace App\Enums;

/**
 * De vier rollen uit het bouwplan. De waarden zijn ook de rolnamen in
 * spatie/laravel-permission.
 */
enum Role: string
{
    case Eigenaar = 'eigenaar';
    case Trainer = 'trainer';
    case Ouder = 'ouder';
    case Speler = 'speler';

    public function label(): string
    {
        return match ($this) {
            self::Eigenaar => 'Eigenaar',
            self::Trainer => 'Trainer',
            self::Ouder => 'Ouder',
            self::Speler => 'Speler',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
