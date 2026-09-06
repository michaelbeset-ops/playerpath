<?php

namespace App\Enums;

/**
 * Wat voor product een school verkoopt.
 *
 * Het type bepaalt hoe het product zich gedraagt, niet alleen hoe het heet:
 * een abonnement loopt door en brengt telkens een rekening voort, een
 * rittenkaart heeft beurten die opraken, en een kamp is één keer betalen.
 *
 * **Hernoem een waarde nooit** zonder migratie: hij staat opgeslagen in
 * `products.type` en in de aankopen die eraan hangen.
 */
enum ProductType: string
{
    case Abonnement = 'abonnement';
    case Rittenkaart = 'rittenkaart';
    case LosseTraining = 'losse_training';
    case Kamp = 'kamp';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Abonnement => 'Abonnement',
            self::Rittenkaart => 'Rittenkaart',
            self::LosseTraining => 'Losse training',
            self::Kamp => 'Kamp of clinic',
            self::Overig => 'Overig',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Abonnement => 'Loopt door en brengt telkens een rekening voort.',
            self::Rittenkaart => 'Een aantal beurten die opraken zodra een trainer iemand aanwezig meldt.',
            self::LosseTraining => 'Eén training, één keer betalen.',
            self::Kamp => 'Een kamp, clinic of toernooi: één bedrag, één periode.',
            self::Overig => 'Kleding, materiaal of iets anders dat je verkoopt.',
        };
    }

    /** Alleen een abonnement heeft een frequentie. */
    public function needsInterval(): bool
    {
        return $this === self::Abonnement;
    }

    /** Alleen een rittenkaart heeft beurten. */
    public function needsCredits(): bool
    {
        return $this === self::Rittenkaart;
    }

    /**
     * Wordt dit product als abonnement toegekend, of als eenmalige aankoop?
     *
     * Een abonnement heeft zijn eigen administratie (Subscription) met termijnen
     * en incasso; de rest is een aankoop die één rekening oplevert.
     */
    public function isSubscription(): bool
    {
        return $this === self::Abonnement;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
