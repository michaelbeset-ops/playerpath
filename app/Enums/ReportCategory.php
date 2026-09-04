<?php

namespace App\Enums;

/**
 * De categorieën waarop een trainer scoort.
 *
 * Keeper en veldspeler hebben elk hun eigen zes. Zes is bewust: het past op
 * één scherm zonder scrollen, en dat is de voorwaarde voor het 30-seconden-
 * rapport uit het bouwplan.
 */
enum ReportCategory: string
{
    // Keeper
    case Reflexen = 'reflexen';
    case Uitkomen = 'uitkomen';
    case Voetenwerk = 'voetenwerk';
    case EenTegenEen = 'een_tegen_een';
    case HogeBallen = 'hoge_ballen';
    case Communicatie = 'communicatie';

    // Veldspeler
    case Techniek = 'techniek';
    case Inzicht = 'inzicht';
    case Passing = 'passing';
    case Afwerking = 'afwerking';
    case Snelheid = 'snelheid';
    case Mentaliteit = 'mentaliteit';

    public function label(): string
    {
        return match ($this) {
            self::Reflexen => 'Reflexen',
            self::Uitkomen => 'Uitkomen',
            self::Voetenwerk => 'Voetenwerk',
            self::EenTegenEen => '1-op-1',
            self::HogeBallen => 'Hoge ballen',
            self::Communicatie => 'Communicatie',
            self::Techniek => 'Techniek',
            self::Inzicht => 'Inzicht',
            self::Passing => 'Passing',
            self::Afwerking => 'Afwerking',
            self::Snelheid => 'Snelheid',
            self::Mentaliteit => 'Mentaliteit',
        };
    }

    /** Korte uitleg, zodat elke trainer hetzelfde bedoelt met een cijfer. */
    public function hint(): string
    {
        return match ($this) {
            self::Reflexen => 'Reageren op schoten dichtbij',
            self::Uitkomen => 'Keuze en timing bij uitkomen',
            self::Voetenwerk => 'Voetenwerk en verplaatsing in het doel',
            self::EenTegenEen => 'Duel met een doorgebroken spits',
            self::HogeBallen => 'Voorzetten en hoge ballen pakken',
            self::Communicatie => 'Coachen en organiseren van de verdediging',
            self::Techniek => 'Balbeheersing en eerste aanname',
            self::Inzicht => 'Positiekeuze en spellezen',
            self::Passing => 'Kort en lang aanspelen',
            self::Afwerking => 'Kansen afmaken',
            self::Snelheid => 'Actiesnelheid met en zonder bal',
            self::Mentaliteit => 'Inzet, houding en coachbaarheid',
        };
    }

    /** @return list<self> */
    public static function forPosition(PlayerPosition $position): array
    {
        return match ($position) {
            PlayerPosition::Keeper => [
                self::Reflexen,
                self::Uitkomen,
                self::Voetenwerk,
                self::EenTegenEen,
                self::HogeBallen,
                self::Communicatie,
            ],
            PlayerPosition::Field => [
                self::Techniek,
                self::Inzicht,
                self::Passing,
                self::Afwerking,
                self::Snelheid,
                self::Mentaliteit,
            ],
        };
    }

    /** @return list<string> */
    public static function valuesForPosition(PlayerPosition $position): array
    {
        return array_map(fn (self $c) => $c->value, self::forPosition($position));
    }
}
