<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Onboarding\SendInvitation;
use App\Enums\Feature;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\School;
use App\Models\User;
use App\Support\Features\Features;
use App\Support\Platform\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gebruikers en functies van één school, gezien vanuit het platform.
 *
 * De school-scope staat hier open, dus elke query wordt expliciet op deze
 * school begrensd. Dat is de keerzijde van de beheeromgeving: wat je binnen de
 * app gratis krijgt, moet je hier zelf zeggen.
 */
class SchoolUserController extends Controller
{
    public function __construct(
        protected Features $features,
        protected PlatformAudit $audit,
    ) {}

    public function index(School $school): Response
    {
        $this->authorize('platform.manageSchools');

        return Inertia::render('platform/schools/Users', [
            'school' => ['id' => $school->id, 'name' => $school->name, 'is_active' => $school->is_active],
            'users' => $school->users()
                ->with('roles')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->all(),
                    'is_active' => $user->isActief(),
                    'verified' => $user->email_verified_at !== null,
                    'deactivated_at' => $user->deactivated_at?->format('d-m-Y'),
                ]),
            // Wie is uitgenodigd maar nog niet heeft geactiveerd. Die staat nog
            // niet bij de accounts; zonder deze lijst lijkt de uitnodiging weg.
            'invitations' => Invitation::withoutSchoolScope()
                ->where('school_id', $school->id)
                ->pending()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Invitation $rij) => [
                    'id' => $rij->id,
                    'name' => $rij->name,
                    'email' => $rij->email,
                    'role' => $rij->role,
                    'status' => $rij->status(),
                    'expires_on' => $rij->expires_at->format('d-m-Y'),
                ]),
            'roles' => collect(Role::schoolRoles())
                ->mapWithKeys(fn (Role $rol) => [$rol->value => $rol->label()])
                ->all(),
        ]);
    }

    /** Een account voor deze school aanmaken; het wachtwoord kiest hij zelf. */
    public function store(Request $request, School $school): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, Role::schoolRoles()))],
        ], [
            'email.unique' => 'Er bestaat al een account met dit e-mailadres.',
        ], [
            'name' => 'De naam',
            'email' => 'Het e-mailadres',
            'role' => 'De rol',
        ]);

        // Een uitnodiging met een welkomstmail, geen account met een
        // reset-link: het account ontstaat bij het activeren.
        app(SendInvitation::class)->handle(
            $school,
            $validated['name'],
            $validated['email'],
            $validated['role'],
            $request->user(),
        );

        $this->audit->log('user.invited', "{$validated['name']} <{$validated['email']}> uitgenodigd als {$validated['role']}", $school);

        return back()->with('status', "{$validated['name']} krijgt een welkomstmail om het account te activeren.");
    }

    /**
     * Een account aan- of uitzetten.
     *
     * Deactiveren en niet verwijderen: aan een trainer hangen rapporten en aan
     * een ouder de koppeling met zijn kind. Die historie hoort te blijven.
     */
    public function toggle(Request $request, School $school, User $user): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        abort_unless($user->school_id === $school->id, 404);

        $user->forceFill(['deactivated_at' => $user->isActief() ? now() : null])->save();

        $this->audit->log(
            $user->isActief() ? 'user.activated' : 'user.deactivated',
            ($user->isActief() ? 'Account weer aangezet: ' : 'Account gedeactiveerd: ').$this->audit->describeUser($user),
            $school,
        );

        return back()->with('status', $user->isActief()
            ? "{$user->name} kan weer inloggen."
            : "{$user->name} is gedeactiveerd en kan niet meer inloggen.");
    }

    /** Een wachtwoord-reset op gang brengen; jij ziet het wachtwoord nooit. */
    public function reset(Request $request, School $school, User $user): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        abort_unless($user->school_id === $school->id, 404);

        Password::sendResetLink(['email' => $user->email]);

        $this->audit->log('user.password_reset', 'Wachtwoordreset gestuurd naar '.$this->audit->describeUser($user), $school);

        return back()->with('status', "Er is een e-mail naar {$user->email} gestuurd om een wachtwoord te kiezen.");
    }

    /** De functies van deze school aan- of uitzetten. */
    public function features(Request $request, School $school): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        $validated = $request->validate(
            collect(Feature::cases())
                ->mapWithKeys(fn (Feature $f) => ["features.{$f->value}" => ['required', 'boolean']])
                ->all()
        );

        $voor = $this->features->map($school);

        $na = collect(Feature::cases())
            ->mapWithKeys(fn (Feature $f) => [$f->value => (bool) $validated['features'][$f->value]])
            ->all();

        $school->update(['features' => $na]);

        // Alleen loggen als er echt iets veranderde: een logboek vol regels
        // "niets gewijzigd" maakt de regels die er wel toe doen onvindbaar.
        $wijziging = $this->audit->describeFeatureChange($voor, $na);

        if ($wijziging !== null) {
            $this->audit->log('school.features', $wijziging['summary'], $school, $wijziging['details']);
        }

        return back()->with('status', 'De functies van deze school zijn bijgewerkt.');
    }
}
