<?php

namespace App\Http\Controllers\Trainings;

use App\Actions\Payments\StartCheckout;
use App\Actions\Trainings\CancelTrainingEnrollment;
use App\Actions\Trainings\EnrollInTraining;
use App\Enums\TrainingEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Training;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

/**
 * Los inschrijven op een training, door de ouder.
 *
 * Eén scherm: voor welk kind, wat kost het, hoe betaal je, wat gebeurt er.
 * Wat niet kan staat er niet — een kind dat buiten de leeftijd valt is
 * uitgegrijsd met de reden erbij, en een betaalwijze die de school niet
 * aanbiedt (of die zonder provider niet bestaat) ontbreekt. De echte grens
 * ligt in EnrollInTraining; dit scherm laat hem alleen zien.
 */
class TrainingEnrollmentController extends Controller
{
    public function __construct(
        protected EnrollInTraining $inschrijven,
        protected CancelTrainingEnrollment $afmelden,
        protected StartCheckout $checkout,
        protected PaymentGateway $gateway,
    ) {}

    public function show(Request $request, Training $training): Response
    {
        $this->authorize('enroll', $training);

        $kinderen = Player::whereIn('id', $request->user()->visiblePlayerIds())->orderBy('first_name')->get();
        $aanmeldingen = $training->enrollments()->whereIn('player_id', $kinderen->pluck('id'))->get()->keyBy('player_id');
        $inGroep = $training->group_id ? $training->group->players()->pluck('players.id')->all() : [];

        return Inertia::render('trainings/Enroll', [
            'training' => [
                'id' => $training->id,
                'label' => $training->label(),
                'date' => $training->starts_at->translatedFormat('l j F Y'),
                'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
                'location' => $training->location,
                'trainers' => $training->trainers->pluck('name')->all(),
                'price' => Money::format($training->price_cents),
                'is_free' => $training->price_cents === 0,
                'capacity' => $training->capacity,
                'spots_left' => $training->spotsLeft(),
                'is_full' => $training->isFull(),
                'requires_approval' => $training->requires_approval,
                'age_label' => $training->ageLabel(),
                'audience_label' => $training->audience->label(),
                'open' => $training->isOpenForEnrollment(),
            ],
            'children' => $kinderen->map(function (Player $kind) use ($training, $aanmeldingen, $inGroep) {
                $aanmelding = $aanmeldingen->get($kind->id);
                $status = in_array($kind->id, $inGroep, true)
                    ? 'group'
                    : ($aanmelding?->status->isActive() ? $aanmelding->status->value : null);

                return [
                    'id' => $kind->id,
                    'first_name' => $kind->first_name,
                    'status' => $status,
                    'status_label' => $status === 'group' ? 'Zit al in de groep' : $aanmelding?->status->label(),
                    'eligible' => $status === null && $training->acceptsPlayer($kind),
                    'reason' => $training->acceptsPlayer($kind) ? null : $training->rejectionReason($kind),
                    // Op de wachtlijst en er is plek: dan mag hij nu.
                    'invited' => $status === 'waitlisted' && ! $training->isFull(),
                ];
            })->values(),
            'methods' => $this->betaalwijzen($training),
        ]);
    }

    public function store(Request $request, Training $training): HttpResponse|RedirectResponse
    {
        $this->authorize('enroll', $training);

        $eigen = $request->user()->visiblePlayerIds();
        $toegestaan = array_column($this->betaalwijzen($training), 'key');

        $validated = $request->validate([
            'player_id' => ['required', 'integer', Rule::in($eigen)],
            'payment_method' => [Rule::requiredIf($training->price_cents > 0 && $toegestaan !== []), 'nullable', Rule::in($toegestaan)],
        ], [
            'player_id.in' => 'Kies een van je eigen kinderen.',
            'payment_method.required' => 'Kies hoe je betaalt.',
            'payment_method.in' => 'Deze betaalwijze kan niet bij deze training.',
        ], ['player_id' => 'Het kind', 'payment_method' => 'De betaalwijze']);

        $kind = Player::findOrFail($validated['player_id']);

        try {
            $aanmelding = $this->inschrijven->handle($training, $kind, $request->user(), $validated['payment_method'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['player_id' => $e->getMessage()]);
        }

        $wanneer = $training->starts_at->translatedFormat('l j F');

        if ($aanmelding->status === TrainingEnrollmentStatus::Waitlisted) {
            return redirect()->route('trainings.index')
                ->with('status', "De training is vol. {$kind->first_name} staat op de wachtlijst; je hoort het zodra er plek is.");
        }

        if ($aanmelding->status === TrainingEnrollmentStatus::Requested) {
            return redirect()->route('trainings.index')
                ->with('status', "De aanvraag voor {$kind->first_name} is verstuurd. Je hoort het zodra de school hem heeft bekeken; betalen komt daarna.");
        }

        $betaling = $aanmelding->payment;

        if ($betaling === null) {
            return redirect()->route('trainings.index')
                ->with('status', "{$kind->first_name} is ingeschreven voor {$training->label()} op {$wanneer}.");
        }

        if ($aanmelding->paysCash()) {
            return redirect()->route('trainings.index')
                ->with('status', "{$kind->first_name} is ingeschreven voor {$wanneer}. Je rekent {$training->formattedPrice()} contant af bij de training.");
        }

        try {
            $checkout = $this->checkout->handle($betaling, route('billing.return', $betaling));
        } catch (Throwable $e) {
            report($e);
            $checkout = null;
        }

        if ($checkout === null) {
            return redirect()->route('billing.index')
                ->with('status', "{$kind->first_name} is ingeschreven. De betaling staat klaar in je overzicht.");
        }

        return Inertia::location($checkout);
    }

    /** Afmelden voor een losse training: de plek gaat terug in de pot. */
    public function destroy(Request $request, Training $training, Player $player): RedirectResponse
    {
        $this->authorize('view', $training);

        abort_unless(in_array($player->id, $request->user()->visiblePlayerIds(), strict: true), 403);

        $aanmelding = $training->enrollments()->where('player_id', $player->id)->active()->first();

        abort_if($aanmelding === null, 404);
        abort_if($training->hasPassed(), 422, 'Deze training is al geweest.');

        $this->afmelden->handle($aanmelding);

        return back()->with('status', "{$player->first_name} is afgemeld voor {$training->label()}.");
    }

    /**
     * Wat de school aanbiedt én wat kan: zonder provider bestaat online niet.
     *
     * @return list<array{key: string, label: string, hint: string}>
     */
    protected function betaalwijzen(Training $training): array
    {
        if ($training->price_cents <= 0) {
            return [];
        }

        $uit = [];

        if ($training->allowsPayment('online') && $this->gateway->isConnected()) {
            $uit[] = ['key' => 'online', 'label' => 'Direct online betalen', 'hint' => 'Je rekent nu af via '.$this->gateway->name().'.'];
        }

        if ($training->allowsPayment('cash')) {
            $uit[] = ['key' => 'cash', 'label' => 'Contant bij de training', 'hint' => 'Je betaalt aan de trainer ter plaatse.'];
        }

        return $uit;
    }
}
