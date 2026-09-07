<?php

namespace App\Enums;

/**
 * Wat voor aanbod een school verkoopt.
 *
 * Het type bepaalt hoe het zich gedraagt, niet alleen hoe het heet: een blok
 * loopt van een datum tot een datum en levert een reeks trainingen op, een
 * rittenkaart heeft beurten die opraken, en doorlopende training houdt niet
 * vanzelf op.
 *
 * **Betalen staat er los van** (zie BillingType): een blok kan eenmalig zijn of
 * per maand. Het oude type `abonnement` beschreef juist die betaalwijze en heet
 * daarom nu `doorlopend`.
 *
 * **Hernoem een waarde nooit** zonder migratie: hij staat opgeslagen in
 * `products.type` en in de aankopen die eraan hangen.
 */
enum ProductType: string
{
    case Doorlopend = 'doorlopend';
    case Blok = 'blok';
    case Kamp = 'kamp';
    case LosseTraining = 'losse_training';
    case Privetraining = 'privetraining';
    case SmallGroup = 'small_group';
    case Rittenkaart = 'rittenkaart';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Doorlopend => 'Doorlopende training',
            self::Blok => 'Blok',
            self::Kamp => 'Kamp of clinic',
            self::LosseTraining => 'Losse training',
            self::Privetraining => 'Privétraining',
            self::SmallGroup => 'Small group',
            self::Rittenkaart => 'Rittenkaart',
            self::Overig => 'Overig',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Doorlopend => 'Loopt door tot iemand hem stopt, met elke termijn een rekening.',
            self::Blok => 'Een vaste reeks trainingen tussen twee datums, bijvoorbeeld zes weken.',
            self::Kamp => 'Een kamp, clinic of toernooi op vaste dagen.',
            self::LosseTraining => 'Eén training, één keer betalen.',
            self::Privetraining => 'Eén op één, op een moment dat jullie samen kiezen.',
            self::SmallGroup => 'Met een klein groepje, met een vaste reeks momenten.',
            self::Rittenkaart => 'Een aantal beurten die opraken zodra een trainer iemand aanwezig meldt.',
            self::Overig => 'Kleding, materiaal of iets anders dat je verkoopt.',
        };
    }

    /** Alleen doorlopende training heeft een frequentie. */
    public function needsInterval(): bool
    {
        return $this === self::Doorlopend;
    }

    /** Alleen een rittenkaart heeft beurten. */
    public function needsCredits(): bool
    {
        return $this === self::Rittenkaart;
    }

    /**
     * Loopt dit tussen twee datums?
     *
     * Een blok, een kamp en een small group hebben een begin en een eind — en
     * dus ook een rooster, plekken en een groep die erbij hoort.
     */
    public function hasPeriod(): bool
    {
        return in_array($this, [self::Blok, self::Kamp, self::SmallGroup], strict: true);
    }

    /**
     * Hoort hier een groep met trainingen bij?
     *
     * Dat is wat het aanbod aan de rest van de app knoopt: de trainingen hangen
     * onder die groep, en daardoor blijven aanwezigheid en rapporten werken
     * zoals ze altijd al deden.
     */
    public function hasSchedule(): bool
    {
        return $this->hasPeriod();
    }

    /** Kun je hier plekken voor tellen? */
    public function hasCapacity(): bool
    {
        return in_array(
            $this,
            [self::Blok, self::Kamp, self::SmallGroup, self::Doorlopend, self::LosseTraining],
            strict: true,
        );
    }

    /**
     * Wordt dit als abonnement toegekend, of als eenmalige aankoop?
     *
     * Let op: dit gaat over de administratie en hangt aan de betaalwijze, niet
     * aan het soort. Een blok dat per maand betaald wordt is een abonnement met
     * een einddatum.
     */
    public function isSubscription(): bool
    {
        return $this === self::Doorlopend;
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
