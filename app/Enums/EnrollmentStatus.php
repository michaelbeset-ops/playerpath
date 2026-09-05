<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Nieuw',
            self::Approved => 'Goedgekeurd',
            self::Declined => 'Afgewezen',
        };
    }
}
