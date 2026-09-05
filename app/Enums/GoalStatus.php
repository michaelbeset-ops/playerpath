<?php

namespace App\Enums;

enum GoalStatus: string
{
    case Active = 'active';
    case Achieved = 'achieved';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actief',
            self::Achieved => 'Gehaald',
            self::Missed => 'Niet gehaald',
            self::Cancelled => 'Gestopt',
        };
    }
}
