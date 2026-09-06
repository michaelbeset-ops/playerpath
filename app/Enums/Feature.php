<?php

namespace App\Enums;

/**
 * Functies die per school aan of uit kunnen.
 *
 * Een feature toevoegen is één case erbij en een regel in de drie matches
 * eronder. Verder hoeft er niets: het beheerscherm, de opslag, de middleware
 * en het menu lezen allemaal deze enum. Er staat dus nergens een lijstje met
 * featurenamen dat kan verouderen.
 *
 * De sleutel wordt opgeslagen in `schools.features`, dus hernoem hem nooit
 * zonder een migratie: een school die "kalender" uit had staan zou anders
 * stilzwijgend de kalender terugkrijgen.
 */
enum Feature: string
{
    case Ontwikkeling = 'ontwikkeling';
    case Kalender = 'kalender';
    case Betalingen = 'betalingen';
    case Inschrijvingen = 'inschrijvingen';
    case Mededelingen = 'mededelingen';
    case Exports = 'exports';

    public function label(): string
    {
        return match ($this) {
            self::Ontwikkeling => 'Spelerontwikkeling',
            self::Kalender => 'Kalender',
            self::Betalingen => 'Betalingen',
            self::Inschrijvingen => 'Online inschrijven',
            self::Mededelingen => 'Mededelingen',
            self::Exports => 'Overzichten exporteren',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ontwikkeling => 'Rapporten, spelerskaart, doelen en de voortgang die ouders zien.',
            self::Kalender => 'De maandweergave van trainingen. Het rooster zelf blijft altijd werken.',
            self::Betalingen => 'Tarieven, abonnementen, facturen en incasso, inclusief de nachtelijke loop.',
            self::Inschrijvingen => 'Het openbare inschrijfformulier en de inbox met aanmeldingen.',
            self::Mededelingen => 'Berichten aan ouders en het afzeggen van een training met bericht.',
            self::Exports => 'Overzichten downloaden als Excel of CSV.',
        };
    }

    /**
     * Staat deze functie standaard aan?
     *
     * Alles staat aan tenzij anders gezegd: een nieuwe feature mag nooit
     * stilletjes uit staan bij scholen die er niets van weten. Uitzetten is
     * een bewuste keuze van de platformbeheerder.
     */
    public function defaultEnabled(): bool
    {
        return true;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
