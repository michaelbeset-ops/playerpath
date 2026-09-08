<?php

namespace App\Support\Onboarding;

use App\Models\School;

/**
 * Hoe ver een school is met opstarten.
 *
 * Eén plek, gelezen door het dashboard, de rondleiding, de voorbeelddata en het
 * platformbeheer. Zelfde afspraak als bij `RatingSettings` en
 * `EnrollmentSettings`: **de standaarden staan in code**, in
 * `schools.onboarding` staat alleen wat deze school daadwerkelijk deed. Een
 * nieuw veld krijgt zo bij elke bestaande school vanzelf zijn standaard, en een
 * school die niets deed heeft een lege kolom in plaats van een rij vinkjes die
 * niets zeggen.
 *
 * Wat hier níét in staat: of er spelers zijn, of er een rapport is, of de
 * inschrijfwizard af is. Dat zijn feiten over de database, en die vraag je aan
 * de database — anders krijg je twee waarheden die uit elkaar lopen zodra
 * iemand zijn enige speler verwijdert. Hier staat alleen wat je nergens anders
 * kunt aflezen: dat iemand iets heeft weggeklikt of gezien.
 */
class OnboardingState
{
    /** @var array<string, mixed> */
    public const STANDAARD = [
        // Wanneer de startchecklist met de hand is weggeklikt. Terughalen kan.
        'checklist_dismissed_at' => null,
        // Wanneer de checklist voor het eerst helemaal af was; daarna is de
        // felicitatie gezien en komt hij niet meer terug.
        'checklist_completed_at' => null,
        // Wanneer de rondleiding is afgerond of overgeslagen. Opnieuw starten
        // kan altijd, via de vraagtekenknop in de balk.
        'tour_seen_at' => null,
        // Bij welke stap iemand was: de rondleiding loopt over veertien
        // schermen, en wie halverwege wegklikt hoort daar te kunnen hervatten.
        'tour_step' => 0,
        // De hoogste wizardstap die is opgeslagen of overgeslagen, zodat het
        // menu-item weer opent waar je was.
        'wizard_step' => 0,
        // Wanneer de voorbeelddata is neergezet, en wanneer hij is opgeruimd.
        'demo_seeded_at' => null,
        'demo_removed_at' => null,
    ];

    /** @var array<string, mixed> */
    protected array $waarden;

    public function __construct(protected ?School $school)
    {
        $this->waarden = array_replace(self::STANDAARD, $school?->onboarding ?? []);
    }

    public static function for(?School $school): self
    {
        return new self($school);
    }

    /**
     * @param  array<string, mixed>  $waarden
     */
    public static function save(School $school, array $waarden): void
    {
        $school->forceFill([
            'onboarding' => array_replace($school->onboarding ?? [], $waarden),
        ])->save();
    }

    public static function mark(School $school, string $sleutel): void
    {
        self::save($school, [$sleutel => now()->toIso8601String()]);
    }

    public static function clear(School $school, string $sleutel): void
    {
        self::save($school, [$sleutel => null]);
    }

    public function get(string $sleutel): mixed
    {
        return $this->waarden[$sleutel] ?? null;
    }

    public function has(string $sleutel): bool
    {
        return $this->get($sleutel) !== null;
    }

    public function checklistDismissed(): bool
    {
        return $this->has('checklist_dismissed_at');
    }

    public function checklistCompleted(): bool
    {
        return $this->has('checklist_completed_at');
    }

    public function tourSeen(): bool
    {
        return $this->has('tour_seen_at');
    }

    public function tourStep(): int
    {
        return (int) ($this->get('tour_step') ?? 0);
    }

    public function wizardStep(): int
    {
        return (int) ($this->get('wizard_step') ?? 0);
    }

    /** Is de voorbeelddata neergezet en nog niet opgeruimd? */
    public function hasDemoData(): bool
    {
        return $this->has('demo_seeded_at') && ! $this->has('demo_removed_at');
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->waarden;
    }
}
