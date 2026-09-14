<?php

namespace App\Support\Platform;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Onboarding\OnboardingState;
use App\Support\Onboarding\OnboardingTour;
use App\Support\Tenancy\Tenancy;

/**
 * Hoe ver een school is met opstarten, gezien vanuit het platform.
 *
 * Waarvoor dit er is: zien welke scholen vastlopen, zodat je kunt bijspringen
 * vóórdat ze afhaken. Een school die na twee weken op één van de zeven staat
 * heeft geen mail nodig maar een telefoontje.
 *
 * Het rekent met dezelfde stappen als de startchecklist die de school
 * zelf ziet - en dus ook met dezelfde regel dat **voorbeelddata niet meetelt**.
 * Zou dat hier anders zijn, dan staat elke verse school hier op alles gedaan
 * en zie je nooit meer wie er hulp nodig heeft.
 *
 * De teller draait in de platformmodus, waar de global scope openstaat; elke
 * query begrenst zichzelf daarom expliciet op deze school. Zonder dat telt hij
 * alle scholen bij elkaar op.
 */
class OnboardingProgress
{
    /** In dezelfde volgorde als de checklist die de school zelf ziet. */
    public const STAPPEN = ['school', 'location', 'group', 'player', 'training', 'report', 'guardian', 'product'];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @return array<string, mixed>
     */
    public function for(School $school): array
    {
        $gedaan = [
            'school' => EnrollmentSettings::for($school)->isCompleted(),
            'location' => $this->bestaat(Location::class, $school),
            'group' => $this->bestaat(Group::class, $school),
            'player' => $this->bestaat(Player::class, $school),
            'training' => $this->bestaat(Training::class, $school),
            // Een rapport, of bij de inzetkaart de eerste inzetpunten.
            'report' => $this->bestaat(Report::class, $school) || $this->bestaat(\App\Models\EffortRating::class, $school),
            'guardian' => $this->heeftRol($school, Role::Ouder),
            'product' => $this->bestaat(Product::class, $school),
        ];

        $stand = OnboardingState::for($school);
        $aantal = count(array_filter($gedaan));

        return [
            'done' => $aantal,
            'total' => count(self::STAPPEN),
            'percentage' => (int) round($aantal / count(self::STAPPEN) * 100),
            'steps' => $gedaan,
            'labels' => [
                'school' => 'Wizard afgerond',
                'location' => 'Eerste locatie',
                'group' => 'Eerste groep',
                'player' => 'Eerste speler',
                'training' => 'Eerste training',
                'report' => 'Eerste rapport',
                'guardian' => 'Ouder uitgenodigd',
                'product' => 'Eerste aanbod',
            ],
            // Wat de school zelf heeft weggeklikt of gezien; verklaart waarom
            // iemand ergens blijft hangen.
            'checklistDismissed' => $stand->checklistDismissed(),
            // Waar ze zijn in de rondleiding en de wizard: "stap 4 van 14" zegt
            // meer dan "nog niet gezien".
            'tourStep' => $stand->tourStep(),
            'tourTotal' => count(app(OnboardingTour::class)->steps($school)),
            'wizardStep' => $stand->wizardStep(),
            'wizardTotal' => count(EnrollmentSettings::STAPPEN),
            'wizardCompleted' => EnrollmentSettings::for($school)->isCompleted(),
            'checklistCompleted' => $stand->checklistCompleted(),
            'tourSeen' => $stand->tourSeen(),
            'hasDemoData' => $stand->hasDemoData(),
        ];
    }

    /**
     * Bestaat er een echte rij van dit soort bij deze school?
     *
     * `withoutSchoolScope()` plus een expliciete `where`: in de platformmodus
     * staat de scope open, en dan zou dit alle scholen tellen.
     *
     * @param  class-string  $model
     */
    protected function bestaat(string $model, School $school): bool
    {
        return $model::withoutSchoolScope()
            ->where('school_id', $school->id)
            ->where('is_demo', false)
            ->exists();
    }

    protected function heeftRol(School $school, Role $rol): bool
    {
        return User::where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', $rol->value))
            ->exists()
            || Invitation::withoutSchoolScope()
                ->where('school_id', $school->id)
                ->where('role', $rol->value)
                ->exists();
    }
}
