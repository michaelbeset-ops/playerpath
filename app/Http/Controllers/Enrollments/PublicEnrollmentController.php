<?php

namespace App\Http\Controllers\Enrollments;

use App\Enums\PaymentMethod;
use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use App\Notifications\NieuweInschrijving;
use App\Support\Money\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het openbare inschrijfformulier van een school: /inschrijven/{slug}.
 *
 * Geen inlog. De school komt uit de slug in de URL — dat is hier wél de
 * bron, want er is geen ingelogde gebruiker. Alles wat het formulier
 * oplevert is een inschrijving die de eigenaar nog moet goedkeuren; er komt
 * dus nooit ongevraagd iemand in het ledenbestand.
 */
class PublicEnrollmentController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function show(School $school): Response
    {
        abort_unless($school->is_active, 404);

        $tarieven = $this->tenancy->forSchool($school, fn () => Plan::where('is_active', true)
            ->orderBy('amount_cents')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'amount' => Money::format($plan->amount_cents),
                'interval' => $plan->interval->label(),
            ]));

        return Inertia::render('enrollments/Public', [
            'school' => ['name' => $school->name, 'slug' => $school->slug],
            'plans' => $tarieven,
            'positions' => PlayerPosition::options(),
            'methods' => PaymentMethod::options(),
            'submitted' => (bool) session('enrollment_submitted'),
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        abort_unless($school->is_active, 404);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:'.now()->subYears(30)->toDateString()],
            'position' => ['required', Rule::enum(PlayerPosition::class)],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_email' => ['required', 'email', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->where('school_id', $school->id)->where('is_active', true)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['accepted'],
        ], [
            'privacy.accepted' => 'Je moet akkoord gaan met het gebruik van de gegevens.',
            'date_of_birth.before' => 'De geboortedatum moet in het verleden liggen.',
        ], [
            'first_name' => 'De voornaam',
            'last_name' => 'De achternaam',
            'date_of_birth' => 'De geboortedatum',
            'position' => 'De positie',
            'guardian_name' => 'Je naam',
            'guardian_email' => 'Je e-mailadres',
            'guardian_phone' => 'Je telefoonnummer',
            'relationship' => 'De relatie',
            'plan_id' => 'Het tarief',
            'payment_method' => 'De betaalmethode',
            'note' => 'De opmerking',
        ]);

        unset($validated['privacy']);

        $enrollment = $this->tenancy->forSchool($school, fn () => Enrollment::create($validated));

        // De eigenaar hoort het meteen, in de app en per mail.
        $eigenaren = User::where('school_id', $school->id)->role(Role::Eigenaar->value)->get();
        Notification::send($eigenaren, new NieuweInschrijving($enrollment));

        return redirect()
            ->route('enroll.show', $school)
            ->with('enrollment_submitted', true);
    }
}
