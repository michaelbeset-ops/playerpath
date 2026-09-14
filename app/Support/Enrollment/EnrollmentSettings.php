<?php

namespace App\Support\Enrollment;

use App\Enums\ProductType;
use App\Models\School;

/**
 * Hoe deze school inschrijft en int.
 *
 * Scholen verschillen sterk in hoe ze dat doen, maar gebruiken dezelfde
 * bouwstenen: proefles of niet, inschrijfgeld, termijnen of maandelijks,
 * opzegtermijn, restitutie, kortingen, wachtlijst. Dit is de ene plek waar die
 * keuzes staan. De rest van de app leest hieruit en heeft nergens een eigen
 * `if ($school->id === 3)`.
 *
 * Zelfde afspraak als bij de rekenkern en de functies per school: **de
 * standaarden staan in code**, in `schools.enrollment_settings` staan alleen
 * de antwoorden die de school gaf. Een nieuwe instelling krijgt dan bij elke
 * bestaande school vanzelf zijn standaard.
 *
 * De standaarden zijn de gangbare praktijk bij Nederlandse voetbalscholen:
 * handmatig goedkeuren, geen inschrijfgeld, vooraf betalen, een maand
 * opzegtermijn, kosteloos annuleren tot twee weken voor de start, geen
 * restitutie bij ziekte, en een wachtlijst die pas betaalt bij plaatsing.
 */
class EnrollmentSettings
{
    /**
     * De stappen van de wizard, in volgorde.
     *
     * `school`, `groepen` en `trainers` slaan niets in deze klasse op: die
     * stappen zetten de school zelf, haar groepen en haar uitnodigingen. Ze
     * lopen wel mee in de wizard, want samen is dit "je school inrichten" - en
     * een aparte intake-wizard ernaast zou betekenen dat er twee plekken zijn
     * waar je hetzelfde instelt. De eerste stap
     * zet de naam, het logo, de merkkleur en de eerste locatie, en die horen
     * bij de school zelf en niet bij "hoe schrijf je in". Hij loopt wel mee in
     * de wizard, want het is de eerste vraag die een nieuwe school hoort te
     * krijgen - en een aparte intake-wizard ernaast zou betekenen dat er twee
     * plekken zijn waar je hetzelfde instelt.
     */
    public const STAPPEN = ['school', 'aanbod', 'kosten', 'betalen', 'annuleren', 'kortingen', 'formulier', 'kaart', 'groepen', 'trainers'];

    /** @var array<string, mixed> */
    public const STANDAARD = [
        // 1. Inschrijven: wat je aanbiedt, en wanneer een ouder kan instappen.
        'offering_types' => ['blok', 'doorlopend', 'rittenkaart', 'kamp', 'privetraining', 'proefles'],
        'trial' => ['enabled' => true, 'amount_cents' => 0],
        // Op welke momenten een ouder zich kan aanmelden. Meerdere mag: een
        // school die het hele jaar laat instromen kan ook kampen hebben.
        'enrollment' => ['moments' => ['anytime', 'before_block']],
        // De standaard voor een nieuwe training: mag een ouder zijn kind los
        // aanmelden, hoe betaalt hij dan, en kijk jij er eerst naar. Per
        // training kun je ervan afwijken; dit vult het formulier voor.
        'training_enrollment' => ['open' => false, 'payment_methods' => ['online', 'cash'], 'requires_approval' => false],

        // 2. Kosten erbij
        'registration_fee' => ['enabled' => false, 'amount_cents' => 0],
        'kit' => ['enabled' => false, 'amount_cents' => 0],

        // 3. Betalen. Meerdere betaalvormen mogen naast elkaar: een ouder
        // kiest dan bij het inschrijven. `type` is de oude, enkelvoudige
        // instelling; wie hem nog heeft staan krijgt hem via paymentTypes().
        'default_payment' => ['types' => ['upfront'], 'installments' => 3, 'interval' => 'month'],
        'auto_renew_block' => false,
        'notice_months' => 1,
        'chargeback_fee' => ['enabled' => false, 'amount_cents' => 0],
        // Na een mislukte betaling: herinneringen na zoveel dagen, met een nieuwe link.
        'dunning' => ['days' => [3, 7, 14]],
        // Handmatig goedkeuren is de standaard; een school die het vertrouwt
        // zet het op automatisch en dan bevestigt de betaling de inschrijving.
        'approval' => 'manual',

        // 4. Annuleren
        'cancellation' => ['free_until_days' => 14, 'retain_percent' => 50],
        'absence' => 'none',

        // 5. Kortingen
        'discounts' => [
            'family' => ['enabled' => false, 'percent' => 10],
            'early' => ['enabled' => false, 'percent' => 10, 'days_before' => 30],
            'volume' => ['enabled' => false, 'percent' => 10, 'from_count' => 2],
            'code' => ['enabled' => false],
            'stackable' => false,
        ],

        // 6. Formulier
        'capacity' => ['waitlist' => true, 'pay_on_placement' => true, 'invitation_days' => 3],
        'fields' => ['kledingmaat' => 'off', 'positie' => 'required', 'niveau' => 'optional', 'medisch' => 'optional'],

        // Wanneer de wizard voor het eerst is afgerond; null zolang niet.
        'completed_at' => null,
    ];

    /** Wat een aanmeldveld kan zijn. */
    public const VELD_STANDEN = ['off', 'optional', 'required'];

    /** Wat er bij ziekte of afwezigheid gebeurt. */
    public const AFWEZIGHEID = ['none', 'refund', 'makeup'];

    /** De betaalvormen die een school als standaard kan kiezen. */
    public const BETAALVORMEN = ['upfront', 'installments', 'monthly'];

    /** Wanneer een ouder zich kan aanmelden. */
    public const INSTAPMOMENTEN = ['anytime', 'before_block', 'single_training', 'camp'];

    /** @var array<string, mixed> */
    protected array $waarden;

    /**
     * Wat de school zelf opsloeg, zonder de standaarden erdoorheen. Nodig om
     * een oude, enkelvoudige keuze te herkennen: na het samenvoegen staat de
     * nieuwe standaard er altijd, en dan zie je niet meer wat er echt gekozen is.
     *
     * @var array<string, mixed>
     */
    protected array $opgeslagen;

    public function __construct(?School $school = null)
    {
        $this->opgeslagen = $school?->enrollment_settings ?? [];
        $this->waarden = self::samenvoegen(self::STANDAARD, $this->opgeslagen);
    }

    public static function for(?School $school): self
    {
        return new self($school);
    }

    /**
     * Standaard en opgeslagen waarden laag voor laag samenvoegen.
     *
     * `array_replace` op het bovenste niveau zou een school die alleen
     * `trial.amount_cents` zette haar `trial.enabled` laten verliezen.
     *
     * @param  array<string, mixed>  $standaard
     * @param  array<string, mixed>  $opgeslagen
     * @return array<string, mixed>
     */
    protected static function samenvoegen(array $standaard, array $opgeslagen): array
    {
        foreach ($opgeslagen as $sleutel => $waarde) {
            $isLijst = is_array($waarde) && array_is_list($waarde);

            if (is_array($waarde) && ! $isLijst && isset($standaard[$sleutel]) && is_array($standaard[$sleutel])) {
                $standaard[$sleutel] = self::samenvoegen($standaard[$sleutel], $waarde);
            } else {
                $standaard[$sleutel] = $waarde;
            }
        }

        return $standaard;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->waarden;
    }

    /** @return array<string, mixed> */
    public function get(string $sleutel): mixed
    {
        return $this->waarden[$sleutel] ?? null;
    }

    public function isCompleted(): bool
    {
        return $this->waarden['completed_at'] !== null;
    }

    /**
     * De aanbodsoorten die deze school gebruikt, als enum.
     *
     * Wat de school niet aanbiedt staat niet in het aanbodformulier: een
     * keuzelijst met acht soorten waarvan je er twee gebruikt is ruis.
     *
     * @return list<ProductType>
     */
    public function offeringTypes(): array
    {
        $gekozen = $this->waarden['offering_types'];

        // "Overig" is altijd beschikbaar: kleding, materiaal. Dat is geen
        // aanbodvorm waar je een keuze over maakt.
        return array_values(array_filter(
            ProductType::cases(),
            fn (ProductType $type) => $type === ProductType::Overig || in_array($type->value, $gekozen, true),
        ));
    }

    public function offers(ProductType $type): bool
    {
        return in_array($type, $this->offeringTypes(), true);
    }

    public function trialEnabled(): bool
    {
        return (bool) $this->waarden['trial']['enabled'] && $this->offers(ProductType::Proefles);
    }

    public function trialAmountCents(): int
    {
        return (int) $this->waarden['trial']['amount_cents'];
    }

    public function registrationFeeCents(): int
    {
        return $this->waarden['registration_fee']['enabled'] ? (int) $this->waarden['registration_fee']['amount_cents'] : 0;
    }

    public function kitCents(): int
    {
        return $this->waarden['kit']['enabled'] ? (int) $this->waarden['kit']['amount_cents'] : 0;
    }

    /**
     * De betaalvormen die een school standaard aanbiedt, altijd als lijst.
     *
     * Een school die de wizard doorliep toen dit nog één keuze was heeft
     * `type` staan; die keuze blijft gelden tot ze hem aanpast.
     *
     * @return list<string>
     */
    public function paymentTypes(): array
    {
        $eigen = $this->opgeslagen['default_payment'] ?? [];
        $lijst = $eigen['types'] ?? (isset($eigen['type']) ? [$eigen['type']] : $this->waarden['default_payment']['types']);

        return array_values(array_intersect(self::BETAALVORMEN, (array) $lijst)) ?: ['upfront'];
    }

    /** @return list<string> */
    public function enrollmentMoments(): array
    {
        return array_values(array_intersect(self::INSTAPMOMENTEN, (array) ($this->waarden['enrollment']['moments'] ?? [])));
    }

    /**
     * Wat een nieuwe training voorgevuld krijgt voor los inschrijven.
     *
     * @return array{open: bool, payment_methods: list<string>, requires_approval: bool}
     */
    public function trainingDefaults(): array
    {
        $t = $this->waarden['training_enrollment'];

        return [
            'open' => (bool) $t['open'],
            'payment_methods' => array_values(array_intersect(['online', 'cash'], (array) $t['payment_methods'])) ?: ['cash'],
            'requires_approval' => (bool) $t['requires_approval'],
        ];
    }

    public function approvesManually(): bool
    {
        return $this->waarden['approval'] !== 'automatic';
    }

    public function noticeMonths(): int
    {
        return (int) $this->waarden['notice_months'];
    }

    public function chargebackFeeCents(): int
    {
        return $this->waarden['chargeback_fee']['enabled'] ? (int) $this->waarden['chargeback_fee']['amount_cents'] : 0;
    }

    public function hasWaitlist(): bool
    {
        return (bool) $this->waarden['capacity']['waitlist'];
    }

    /** Is dit aanmeldveld aan, en zo ja: verplicht? */
    public function field(string $veld): string
    {
        return $this->waarden['fields'][$veld] ?? 'off';
    }

    /**
     * De opgeslagen antwoorden bijwerken met één stap uit de wizard.
     *
     * Er wordt alleen weggeschreven wat de school invulde; de standaarden
     * blijven in code. Zo verschijnt een instelling die er later bijkomt ook
     * bij deze school, in plaats van dat de eerste opslag hem voor altijd
     * vastzet.
     *
     * @param  array<string, mixed>  $antwoorden
     */
    public static function save(School $school, array $antwoorden): void
    {
        $school->update([
            'enrollment_settings' => self::samenvoegen($school->enrollment_settings ?? [], $antwoorden),
        ]);
    }

    public static function complete(School $school): void
    {
        if (self::for($school)->isCompleted()) {
            return;
        }

        self::save($school, ['completed_at' => now()->toIso8601String()]);
    }
}
