<?php

namespace App\Enums;

/** Wat de trainer afvinkt. */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Aanwezig',
            self::Absent => 'Afwezig',
        };
    }
}
