<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\DeleteSchool;
use App\Enums\Package;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Support\Branding\BrandColor;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Platform\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scholenbeheer voor de platformbeheerder.
 *
 * De school-scope staat hier open (zie EnterPlatform), dus tellingen als
 * "aantal spelers" gaan over alle scholen tegelijk en moeten expliciet per
 * school worden opgehaald — vandaar withCount en niet een losse query per rij.
 */
class SchoolController extends Controller
{
    public function __construct(
        protected Features $features,
        protected PlatformAudit $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('platform.manageSchools');

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),
        ];

        $scholen = School::query()
            ->withCount([
                'players as players_count' => fn ($q) => $q->where('is_active', true),
                'users as trainers_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', Role::Trainer->value)),
                'users as users_count',
            ])
            ->when($filters['search'] !== '', fn ($q) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('slug', 'like', "%{$filters['search']}%")
            ))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get()
            ->map(fn (School $school) => $this->rij($school));

        return Inertia::render('platform/schools/Index', [
            'schools' => $scholen,
            'filters' => $filters,
            'totals' => [
                'schools' => School::count(),
                'active' => School::where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('platform.manageSchools');

        return Inertia::render('platform/schools/Form', [
            'school' => null,
            'packages' => $this->pakketten(),
            'domain' => config('app.domain'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        $validated = $this->valideer($request);

        $school = DB::transaction(function () use ($validated) {
            $school = School::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'is_active' => true,
                'brand_color' => $validated['brand_color'] ?? null,
                'contact_name' => $validated['contact_name'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'package' => $validated['package'] ?? null,
                // Het pakket zet de schakelaars in een keer goed; daarna kun je
                // er per school nog van afwijken.
                'features' => isset($validated['package'])
                    ? Package::from($validated['package'])->featureMap()
                    : null,
            ]);

            // Meteen een eigenaar erbij, als die is opgegeven. Zonder eigenaar
            // kan er niemand in, en dan is een school niet meer dan een rij.
            if (! empty($validated['owner_email'])) {
                $eigenaar = User::create([
                    'school_id' => $school->id,
                    'name' => $validated['owner_name'],
                    'email' => $validated['owner_email'],
                    // Nooit een wachtwoord dat iemand anders kent: hij kiest er
                    // zelf een via wachtwoord-vergeten.
                    'password' => Str::password(32),
                ]);

                $eigenaar->assignRole(Role::Eigenaar->value);
            }

            return $school;
        });

        $this->audit->log('school.created', 'School aangemaakt', $school, [
            'slug' => $school->slug,
            'package' => $school->package,
        ]);

        if (! empty($validated['owner_email'])) {
            Password::sendResetLink(['email' => $validated['owner_email']]);
        }

        return redirect()
            ->route('platform.schools.show', $school)
            ->with('status', empty($validated['owner_email'])
                ? "{$school->name} is aangemaakt. Voeg nog een eigenaar toe voordat iemand kan inloggen."
                : "{$school->name} is aangemaakt. De eigenaar krijgt een e-mail om een wachtwoord te kiezen.");
    }

    public function show(Request $request, School $school): Response
    {
        $this->authorize('platform.manageSchools');

        return Inertia::render('platform/schools/Show', [
            'school' => $this->rij($school->loadCount([
                'players as players_count' => fn ($q) => $q->where('is_active', true),
                'users as trainers_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', Role::Trainer->value)),
                'users as users_count',
            ])),
            'owners' => $school->users()
                ->whereHas('roles', fn ($r) => $r->where('name', Role::Eigenaar->value))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]),
            'features' => $this->features->describe($school),
            'package' => $school->package,
            'packages' => $this->pakketten(),
            // Wat er verdwijnt als je verwijdert. Voor de bevestiging tonen,
            // niet erna: dit is het moment om je te bedenken.
            'deletes' => app(DeleteSchool::class)->summarise($school),
            'domain' => config('app.domain'),
        ]);
    }

    public function edit(School $school): Response
    {
        $this->authorize('platform.manageSchools');

        return Inertia::render('platform/schools/Form', [
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'brand_color' => $school->brand_color,
                'contact_name' => $school->contact_name,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'notes' => $school->notes,
                'package' => $school->package,
            ],
            'packages' => $this->pakketten(),
            'domain' => config('app.domain'),
        ]);
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        $validated = $this->valideer($request, $school);

        $pakketGewijzigd = ($validated['package'] ?? null) !== $school->package;

        $school->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'brand_color' => $validated['brand_color'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'package' => $validated['package'] ?? null,
        ]);

        // Alleen bij een echte pakketwissel de functies opnieuw zetten. Zou dat
        // bij elke opslag gebeuren, dan draai je stilzwijgend een afwijking
        // terug die je bewust hebt gemaakt.
        if ($pakketGewijzigd && isset($validated['package'])) {
            $school->update(['features' => Package::from($validated['package'])->featureMap()]);
        }

        $this->audit->log('school.updated', 'Gegevens bijgewerkt', $school);

        return redirect()
            ->route('platform.schools.show', $school)
            ->with('status', 'De school is bijgewerkt.');
    }

    /**
     * Aan- of uitzetten. Dit is de omkeerbare variant: niemand kan meer
     * inloggen, maar alle gegevens blijven staan. Voor het echte weggooien is
     * er destroy(), met de naam als bevestiging.
     */
    public function toggle(Request $request, School $school): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        $school->update(['is_active' => ! $school->is_active]);

        $this->audit->log(
            $school->is_active ? 'school.activated' : 'school.deactivated',
            $school->is_active ? 'School weer aangezet' : 'School uitgezet',
            $school,
        );

        return back()->with('status', $school->is_active
            ? "{$school->name} staat weer aan."
            : "{$school->name} staat uit. Niemand van die school kan nog inloggen.");
    }

    /**
     * Een school en al zijn gegevens definitief verwijderen.
     *
     * De naam moet letterlijk worden ingetypt. Een bevestigingsvenster klik je
     * weg zonder te lezen; een naam overtypen doe je niet per ongeluk.
     */
    public function destroy(Request $request, School $school, DeleteSchool $verwijder): RedirectResponse
    {
        $this->authorize('platform.manageSchools');

        $request->validate([
            'confirm' => ['required', 'string', Rule::in([$school->name])],
        ], [
            'confirm.in' => 'Typ de naam van de school precies over om te bevestigen.',
        ]);

        $naam = $school->name;

        // Loggen voor het verwijderen: daarna bestaat de school niet meer en
        // kan de regel er niet meer aan gekoppeld worden.
        $this->audit->log('school.deleted', "School {$naam} definitief verwijderd", $school, $verwijder->summarise($school));

        $verwijder->handle($school);

        return redirect()
            ->route('platform.schools.index')
            ->with('status', "{$naam} is verwijderd. Niemand van die school kan nog inloggen.");
    }

    /** @return list<array<string, mixed>> */
    private function pakketten(): array
    {
        return array_map(fn (Package $pakket) => [
            'value' => $pakket->value,
            'label' => $pakket->label(),
            'description' => $pakket->description(),
            'price' => Money::format($pakket->priceCents()),
            'features' => array_map(fn ($f) => $f->label(), $pakket->features()),
        ], Package::cases());
    }

    /** @return array<string, mixed> */
    private function valideer(Request $request, ?School $school = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // De slug wordt straks het subdomein, dus alleen kleine letters,
            // cijfers en streepjes — en uniek over het hele platform.
            'slug' => [
                'required', 'string', 'max:63', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('schools', 'slug')->ignore($school?->id),
            ],
            'brand_color' => ['nullable', 'string', 'max:7'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'package' => ['nullable', Rule::in(Package::values())],
            'owner_name' => ['nullable', 'required_with:owner_email', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ], [
            'slug.regex' => 'Gebruik alleen kleine letters, cijfers en streepjes.',
            'slug.unique' => 'Dit adres is al door een andere school in gebruik.',
            'owner_email.unique' => 'Er bestaat al een account met dit e-mailadres.',
            'owner_name.required_with' => 'Vul ook de naam van de eigenaar in.',
        ], [
            'name' => 'De naam',
            'slug' => 'Het adres',
            'brand_color' => 'De merkkleur',
            'contact_email' => 'Het e-mailadres',
            'owner_email' => 'Het e-mailadres van de eigenaar',
        ]);

        if (! empty($validated['brand_color']) && ! BrandColor::isValid($validated['brand_color'])) {
            throw ValidationException::withMessages(['brand_color' => 'Gebruik een kleurcode als #1BB85E.']);
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function rij(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'slug' => $school->slug,
            'is_active' => $school->is_active,
            'players_count' => $school->players_count ?? 0,
            'trainers_count' => $school->trainers_count ?? 0,
            'users_count' => $school->users_count ?? 0,
            'brand_color' => $school->brand_color,
            'contact_name' => $school->contact_name,
            'contact_email' => $school->contact_email,
            'contact_phone' => $school->contact_phone,
            'notes' => $school->notes,
            'package' => $school->package,
            'created_at' => $school->created_at?->format('d-m-Y'),
        ];
    }
}
