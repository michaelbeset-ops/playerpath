<?php

namespace App\Enums;

/**
 * De rollen. De waarden zijn ook de rolnamen in spatie/laravel-permission.
 *
 * Vier rollen horen bij een school. De vijfde, platformbeheerder, staat er
 * juist buiten: dat account heeft geen school_id en beheert het platform als
 * geheel. Zie Support\Tenancy\Tenancy voor hoe dat in de scope-laag zit.
 */
enum Role: string
{
    case Platformbeheerder = 'platformbeheerder';
    case Eigenaar = 'eigenaar';
    case Trainer = 'trainer';
    case Ouder = 'ouder';
    case Speler = 'speler';

    public function label(): string
    {
        return match ($this) {
            self::Platformbeheerder => 'Platformbeheerder',
            self::Eigenaar => 'Eigenaar',
            self::Trainer => 'Trainer',
            self::Ouder => 'Ouder',
            self::Speler => 'Speler',
        };
    }

    /** Hoort deze rol bij één school? */
    public function belongsToSchool(): bool
    {
        return $this !== self::Platformbeheerder;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * De rollen binnen een school.
     *
     * @return list<self>
     */
    public static function schoolRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $rol) => $rol->belongsToSchool()));
    }
}
