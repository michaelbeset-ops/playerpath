<?php

namespace App\Http\Controllers\Enrollments;

use App\Actions\Enrollments\ApproveEnrollment;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Support\Money\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * De inschrijvingen-inbox van de eigenaar.
 */
class EnrollmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Enrollment::class);

        $vorm = fn (Enrollment $e) => [
            'id' => $e->id,
            'child_name' => $e->child_name,
            'age' => $e->age,
            'date_of_birth' => $e->date_of_birth->format('d-m-Y'),
            'position' => $e->position->label(),
            'guardian_name' => $e->guardian_name,
            'guardian_email' => $e->guardian_email,
            'guardian_phone' => $e->guardian_phone,
            'relationship' => $e->relationship,
            'plan' => $e->plan ? $e->plan->name.' · '.Money::format($e->plan->amount_cents).' '.strtolower($e->plan->interval->label()) : null,
            'payment_method' => $e->payment_method?->label(),
            'note' => $e->note,
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'received' => $e->created_at->diffForHumans(),
            'handled_at' => $e->handled_at?->format('d-m-Y'),
            'player_id' => $e->player_id,
        ];

        $school = app(Tenancy::class)->schoolOrFail();

        return Inertia::render('enrollments/Index', [
            'pending' => Enrollment::pending()->with('plan')->orderBy('created_at')->get()->map($vorm),
            'handled' => Enrollment::where('status', '!=', EnrollmentStatus::Pending->value)
                ->with('plan')->latest('handled_at')->limit(20)->get()->map($vorm),
            'formUrl' => route('enroll.show', $school),
        ]);
    }

    public function approve(Request $request, Enrollment $enrollment, ApproveEnrollment $approve): RedirectResponse
    {
        $this->authorize('update', $enrollment);

        try {
            $player = $approve->handle($enrollment, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['enrollment' => $e->getMessage()]);
        }

        return redirect()
            ->route('players.show', $player)
            ->with('status', "{$player->full_name} is toegevoegd. De ouder heeft een e-mail gekregen om in te loggen.");
    }

    public function decline(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('update', $enrollment);

        abort_unless($enrollment->status === EnrollmentStatus::Pending, 422, 'Deze inschrijving is al afgehandeld.');

        $enrollment->forceFill([
            'status' => EnrollmentStatus::Declined,
            'handled_by_id' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        return back()->with('status', 'De inschrijving is afgewezen.');
    }
}
