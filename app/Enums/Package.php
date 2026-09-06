<?php

namespace App\Enums;

/**
 * De pakketten waarin PlayerPath verkocht wordt.
 *
 * Een pakket is niets meer dan een set functies met een prijs eraan. Het zet
 * de schakelaars uit App\Enums\Feature in één keer goed, zodat een nieuwe
 * school één keuze is in plaats van zes vinkjes.
 *
 * **Een pakket dwingt niets af.** Na het kiezen kun je per school nog steeds
 * losse functies aan- of uitzetten; dan wijkt die school af van zijn pakket en
 * zegt het scherm dat ook. Dat is met opzet: een school die om één extra
 * functie vraagt hoef je niet meteen naar een duurder pakket te duwen, en een
 * afspraak die je maakt hoort niet stilzwijgend teruggedraaid te worden.
 *
 * De prijzen komen uit het marktonderzoek van 6-9-2026 en staan hier alleen om
 * ze in het beheerscherm te tonen. Er wordt niets mee gefactureerd; PlayerPath
 * stuurt zichzelf nog geen rekeningen.
 */
enum Package: string
{
    case Start = 'start';
    case Ontwikkeling = 'ontwikkeling';
    case Academie = 'academie';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Start',
            self::Ontwikkeling => 'Ontwikkeling',
            self::Academie => 'Academie',
        };
    }

    /** In centen, zoals elk bedrag in dit project. */
    public function priceCents(): int
    {
        return match ($this) {
            self::Start => 7900,
            self::Ontwikkeling => 12900,
            self::Academie => 22900,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Start => 'De administratie: inschrijven, betalen, plannen en aanwezigheid.',
            self::Ontwikkeling => 'Alles uit Start, plus rapporten, spelerskaart en doelen.',
            self::Academie => 'Alles, inclusief eigen huisstijl en overzichten voor de boekhouder.',
        };
    }

    /**
     * Welke functies horen bij dit pakket.
     *
     * @return list<Feature>
     */
    public function features(): array
    {
        return match ($this) {
            self::Start => [
                Feature::Betalingen,
                Feature::Inschrijvingen,
                Feature::Mededelingen,
            ],
            self::Ontwikkeling => [
                Feature::Betalingen,
                Feature::Inschrijvingen,
                Feature::Mededelingen,
                Feature::Ontwikkeling,
                Feature::Kalender,
            ],
            self::Academie => Feature::cases(),
        };
    }

    /**
     * De schakelaars zoals dit pakket ze zet.
     *
     * @return array<string, bool>
     */
    public function featureMap(): array
    {
        $aan = array_map(fn (Feature $f) => $f->value, $this->features());

        return collect(Feature::cases())
            ->mapWithKeys(fn (Feature $f) => [$f->value => in_array($f->value, $aan, strict: true)])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
