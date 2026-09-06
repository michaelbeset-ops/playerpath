<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Feature;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Support\Features\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
    public function __construct(protected Features $features) {}

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

        $user = User::create([
            'school_id' => $school->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::password(32),
        ]);

        $user->assignRole($validated['role']);

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('status', "{$user->name} is toegevoegd en krijgt een e-mail om een wachtwoord te kiezen.");
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

        $school->update([
            'features' => collect(Feature::cases())
                ->mapWithKeys(fn (Feature $f) => [$f->value => (bool) $validated['features'][$f->value]])
                ->all(),
        ]);

        return back()->with('status', 'De functies van deze school zijn bijgewerkt.');
    }
}
