<?php

namespace App\Http\Controllers\Enrollments;

use App\Actions\Enrollments\ApproveEnrollment;
use App\Actions\Enrollments\CancelEnrollment;
use App\Actions\Enrollments\DeclineEnrollment;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Support\Money\Money;
use App\Support\Pagination\LoadMore;
use App\Support\Payments\PaymentLink;
use App\Support\Status\TransitionException;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * De inschrijvingen-inbox van de eigenaar.
 *
 * Gegroepeerd op wat er van de school gevraagd wordt: goedkeuren, wachten op
 * betaling (met de mogelijkheid de betaling te markeren), de wachtlijst, en
 * wat afgehandeld is. De statusmachine bepaalt welke knoppen er staan.
 */
class EnrollmentController extends Controller
{
    /** Afgehandeld groeit elk seizoen; dat komt per dertig. */
    public const AFGEHANDELD_PER_PAGINA = 30;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Enrollment::class);

        $relaties = ['product', 'paymentOption', 'order.payments'];

        // Wat nog om een handeling vraagt staat er altijd helemaal: een
        // aanvraag die op pagina drie staat wordt niet goedgekeurd.
        $openStatussen = collect(EnrollmentStatus::cases())
            ->filter(fn (EnrollmentStatus $s) => $s->isOpen())
            ->map(fn (EnrollmentStatus $s) => $s->value)
            ->values()
            ->all();

        $open = Enrollment::with($relaties)->whereIn('status', $openStatussen)->orderBy('created_at')->orderBy('id')->get();

        // Wat afgehandeld is groeit alleen maar; dat komt per pagina.
        [$afgehandeld, $afgehandeldPagina] = LoadMore::slice(
            Enrollment::with($relaties)->whereNotIn('status', $openStatussen)->orderByDesc('updated_at')->orderByDesc('id'),
            $request,
            'handled',
            self::AFGEHANDELD_PER_PAGINA,
        );

        $alle = $open->concat($afgehandeld);

        // Welke rekeningen openstaan zegt de scope op Payment; één query voor alles.
        $openstaand = Payment::query()
            ->outstanding()
            ->whereIn('order_id', $alle->pluck('order_id')->filter()->unique()->values())
            ->pluck('id')
            ->all();

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
            'plan' => $e->product?->name,
            'payment_option' => $e->paymentOption?->describe(),
            'order_total' => $e->order ? Money::format($e->order->total_cents) : null,
            'order_status' => $e->order?->status->label(),
            'waitlist' => $e->status === EnrollmentStatus::Waitlist,
            'payment_method' => $e->payment_method?->label(),
            'note' => $e->note,
            'details' => $e->details,
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'received' => $e->created_at->diffForHumans(),
            'handled_at' => $e->handled_at?->format('d-m-Y'),
            'player_id' => $e->player_id,
            'can_approve' => in_array($e->status, [EnrollmentStatus::AwaitingApproval, EnrollmentStatus::Waitlist], strict: true),
            'can_decline' => $e->status->canTransitionTo(EnrollmentStatus::Declined),
            'can_cancel' => $e->status->isSettled(),
            // Uit de geladen rekeningen, niet per regel een query.
            'first_payment_id' => $e->order?->payments
                ->filter(fn (Payment $p) => in_array($p->id, $openstaand, strict: true))
                ->sortBy([['due_on', 'asc'], ['id', 'asc']])
                ->first()?->id,
        ];

        $school = app(Tenancy::class)->schoolOrFail();

        return Inertia::render('enrollments/Index', [
            'pending' => $open->where('status', EnrollmentStatus::AwaitingApproval)->map($vorm)->values(),
            'awaitingPayment' => $open->whereIn('status', [EnrollmentStatus::AwaitingPayment, EnrollmentStatus::PaymentFailed])->map($vorm)->values(),
            'waitlist' => $open->where('status', EnrollmentStatus::Waitlist)->map($vorm)->values(),
            'handled' => Inertia::merge($afgehandeld->map($vorm)->values()),
            // Het tabblad telt alles wat afgehandeld is, niet alleen wat er geladen is.
            'handledPage' => $afgehandeldPagina,
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

        $melding = $enrollment->refresh()->status === EnrollmentStatus::AwaitingPayment
            ? "De inschrijving van {$player->full_name} is goedgekeurd. De ouder heeft een betaalverzoek gekregen."
            : "{$player->full_name} is ingeschreven. De ouder heeft bericht gekregen.";

        return back()->with('status', $melding);
    }

    public function decline(Request $request, Enrollment $enrollment, DeclineEnrollment $afwijzen): RedirectResponse
    {
        $this->authorize('update', $enrollment);

        try {
            $afwijzen->handle($enrollment, $request->user());
        } catch (TransitionException) {
            return back()->withErrors(['enrollment' => 'Deze aanmelding is al verder en kan niet meer worden afgewezen. Annuleer hem als dat nodig is.']);
        }

        return back()->with('status', 'De aanmelding is afgewezen. De ouder heeft bericht gekregen.');
    }

    /** Annuleren door de school, met hetzelfde restitutiebeleid als voor een ouder. */
    public function cancel(Request $request, Enrollment $enrollment, CancelEnrollment $annuleer): RedirectResponse
    {
        $this->authorize('cancel', $enrollment);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        try {
            $annuleer->handle($enrollment, $request->user(), $validated['reason'] ?? null);
        } catch (TransitionException $e) {
            return back()->withErrors(['enrollment' => $e->getMessage()]);
        }

        $restitutie = (int) ($enrollment->refresh()->refund_cents ?? 0);

        return back()->with('status', $restitutie > 0
            ? 'Geannuleerd. Volgens je beleid komt er '.Money::format($restitutie).' terug; dat betaal je zelf terug.'
            : 'De inschrijving is geannuleerd.');
    }

    /** De betaallink nog eens, bijvoorbeeld om zelf naar de ouder te sturen. */
    public function paymentLink(Enrollment $enrollment, PaymentLink $link): RedirectResponse
    {
        $this->authorize('update', $enrollment);

        $rekening = $enrollment->order?->payments()->outstanding()->orderBy('due_on')->first();

        abort_if($rekening === null, 404);

        return redirect()->to($link->for($rekening));
    }
}
