<?php

namespace App\Http\Controllers\Trainings;

use App\Actions\Trainings\CancelTrainingEnrollment;
use App\Actions\Trainings\ReviewTrainingEnrollment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Wat de school doet met losse aanmeldingen: goedkeuren, afwijzen, van de
 * lijst halen, en afvinken dat er contant is betaald.
 *
 * De poort is `recordAttendance`: wie mag afvinken bij deze training mag ook
 * de aanmeldingen erbij afhandelen. Eén regel voor trainer én eigenaar.
 */
class TrainingRequestController extends Controller
{
    public function __construct(
        protected ReviewTrainingEnrollment $beoordelen,
        protected CancelTrainingEnrollment $afmelden,
    ) {}

    public function approve(Training $training, TrainingEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);
        abort_unless($enrollment->training_id === $training->id, 404);

        try {
            $aanmelding = $this->beoordelen->approve($enrollment);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['enrollment' => $e->getMessage()]);
        }

        $naam = $aanmelding->player->first_name;

        return back()->with('status', $aanmelding->status->value === 'waitlisted'
            ? "De training is inmiddels vol; {$naam} staat op de wachtlijst en de ouders hebben bericht."
            : "{$naam} is ingeschreven. De ouders hebben bericht.");
    }

    public function decline(Request $request, Training $training, TrainingEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);
        abort_unless($enrollment->training_id === $training->id, 404);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:300'],
        ], [], ['message' => 'Het bericht']);

        try {
            $aanmelding = $this->beoordelen->decline($enrollment, $validated['message'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['enrollment' => $e->getMessage()]);
        }

        return back()->with('status', "De aanvraag van {$aanmelding->player->first_name} is afgewezen. De ouders hebben bericht.");
    }

    /** De school haalt iemand van de lijst; de plek gaat naar de wachtlijst. */
    public function remove(Training $training, TrainingEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);
        abort_unless($enrollment->training_id === $training->id, 404);
        abort_unless($enrollment->status->isActive(), 404);

        $this->afmelden->handle($enrollment);

        return back()->with('status', "{$enrollment->player->first_name} staat niet meer op deze training.");
    }

    /**
     * Contant ontvangen. De trainer vinkt het af bij de training; het telt
     * meteen mee in het financiële overzicht als betaald, met "contant" erbij.
     */
    public function cash(Training $training, TrainingEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);
        abort_unless($enrollment->training_id === $training->id, 404);

        $betaling = $enrollment->payment;

        abort_if($betaling === null, 404);

        if ($betaling->status !== PaymentStatus::Paid) {
            $betaling->update([
                'status' => PaymentStatus::Paid,
                'method' => PaymentMethod::Cash,
                'paid_at' => now(),
            ]);
        }

        return back()->with('status', "Contant ontvangen van {$enrollment->player->first_name}.");
    }
}
