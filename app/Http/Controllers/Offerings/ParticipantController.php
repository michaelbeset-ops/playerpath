<?php

namespace App\Http\Controllers\Offerings;

use App\Actions\Enrollments\InviteFromWaitlist;
use App\Actions\Offerings\PromoteParticipation;
use App\Enums\EnrollmentStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Participation;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Wie er meedoet aan een aanbod, en wie er wacht.
 *
 * De wachtlijst is op volgorde van aanmelden, maar **de school beslist wie er
 * doorschuift**. Automatisch de bovenste pakken klinkt eerlijk, tot je bedenkt
 * dat een school dingen weet die wij niet weten: dat er al gebeld is, dat een
 * gezin het inmiddels ergens anders heeft geregeld, dat het kind te jong bleek.
 */
class ParticipantController extends Controller
{
    public function index(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $deelnames = $product->participations()
            ->with(['player', 'purchase.payments', 'subscription.payments'])
            ->orderBy('created_at')
            ->get();

        $vorm = fn (Participation $deelname) => [
            'id' => $deelname->id,
            'player_id' => $deelname->player_id,
            'name' => $deelname->player?->full_name ?? 'Onbekende speler',
            'photo' => $deelname->player?->photo_url,
            'age' => $deelname->player?->age,
            'position' => $deelname->player?->position->label(),
            'since' => $deelname->created_at->format('d-m-Y'),
            ...$this->betaalstand($deelname),
        ];

        return Inertia::render('offerings/Participants', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->label(),
                'amount' => Money::format($product->amount_cents),
                'billing' => $product->billing_type->short(),
                'capacity' => $product->capacity,
                'taken' => $product->participations()->confirmed()->count(),
                'is_full' => $product->isFull(),
                'starts_on' => $product->starts_on?->format('d-m-Y'),
                'ends_on' => $product->ends_on?->format('d-m-Y'),
                'location' => $product->location,
                'group_id' => $product->group?->id,
            ],
            // Inzetkaart: bij een cursus of blok het begin- en eindniveau per kind.
            'courseProgress' => \App\Support\Rating\RatingSettings::for($request->user()->school)->usesEffort() && \App\Support\Progress\CourseProgress::eligible($product),
            'paidCount' => $deelnames
                ->where('status', ParticipationStatus::Confirmed)
                ->filter(fn (Participation $d) => $this->betaalstand($d)['payment_status'] === 'paid')
                ->count(),
            'confirmed' => $deelnames->where('status', ParticipationStatus::Confirmed)->map($vorm)->values(),
            'waitlist' => $deelnames->where('status', ParticipationStatus::Waitlist)->map($vorm)->values(),
            'cancelled' => $deelnames->where('status', ParticipationStatus::Cancelled)->map($vorm)->values(),
        ]);
    }

    /** Iemand van de wachtlijst een plek geven. */
    public function promote(Request $request, Product $product, Participation $participation, PromoteParticipation $doorschuiven): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($participation->product_id === $product->id, 404);
        abort_unless($participation->status === ParticipationStatus::Waitlist, 422, 'Deze deelnemer staat niet op de wachtlijst.');

        // Vol is vol. Zonder deze controle zet een school er per ongeluk een
        // dertiende bij, en dan staat er een kind op het veld waar geen plek
        // voor is.
        abort_if($product->isFull(), 422, 'Er is nog geen plek vrij. Zet eerst iemand van de lijst.');

        // Hoort er een inschrijving bij (via het formulier), dan loopt het via
        // de uitnodiging met betaallink en tijdslimiet. Zonder inschrijving
        // (met de hand op de lijst gezet) gaat het zoals altijd: meteen een plek.
        $inschrijving = $participation->enrollment_id ? Enrollment::find($participation->enrollment_id) : null;

        if ($inschrijving !== null && in_array($inschrijving->status, [EnrollmentStatus::Waitlist, EnrollmentStatus::Expired], strict: true)) {
            try {
                app(InviteFromWaitlist::class)->handle($inschrijving, $request->user());
            } catch (RuntimeException $e) {
                return back()->withErrors(['participation' => $e->getMessage()]);
            }

            return back()->with('status', $participation->player?->first_name.' is uitgenodigd. De ouders hebben een betaallink gekregen; de plek is van hen zodra er betaald is.');
        }

        $doorschuiven->handle($participation);

        return back()->with('status', $participation->player?->first_name.' heeft een plek. De ouders hebben bericht gekregen.');
    }

    /**
     * Iemand van de lijst halen.
     *
     * Bewust geen verwijderen: dat iemand meedeed of gewacht heeft hoort in de
     * historie te blijven, en de rekening die eraan hangt ook.
     */
    public function cancel(Request $request, Product $product, Participation $participation): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($participation->product_id === $product->id, 404);

        $participation->update(['status' => ParticipationStatus::Cancelled]);

        // Een eenmalige rekening voor deze plek hoeft niet meer betaald te worden.
        foreach ($participation->purchase?->payments()->get() ?? [] as $betaling) {
            if ($betaling->status->isPayable() && $betaling->canTransitionTo(PaymentStatus::Cancelled)) {
                $betaling->transitionTo(PaymentStatus::Cancelled);
            }
        }

        // Uit de groep, zodat hij niet op de aanwezigheidslijst van de
        // eerstvolgende training blijft staan.
        if ($product->group !== null && $participation->player instanceof Player) {
            $participation->player->groups()->detach($product->group->id);
        }

        return back()->with('status', $participation->player?->first_name.' staat niet meer op de lijst.');
    }

    /**
     * Heeft deze deelnemer betaald?
     *
     * "Wie moet er nog betalen voor het kamp" is de vraag die een school stelt
     * op de dag dat het kamp begint, en dan wil je niet eerst in het
     * betalingenscherm gaan zoeken. Bij een abonnement kijken we naar de
     * termijn die nu open staat; bij een aankoop is er één rekening.
     *
     * @return array{payment_status: string, payment_label: string, amount: string|null}
     */
    protected function betaalstand(Participation $deelname): array
    {
        $betalingen = $deelname->purchase?->payments ?? $deelname->subscription?->payments;

        if ($betalingen === null || $betalingen->isEmpty()) {
            return ['payment_status' => 'none', 'payment_label' => 'geen rekening', 'amount' => null];
        }

        $open = $betalingen->first(fn (Payment $betaling) => $betaling->status->isOutstanding());

        if ($open === null && $betalingen->every(fn (Payment $betaling) => ! $betaling->status->countsAsRevenue())) {
            return ['payment_status' => 'none', 'payment_label' => $betalingen->first()->status->label(), 'amount' => Money::format($betalingen->sum('amount_cents'))];
        }

        if ($open === null) {
            return [
                'payment_status' => 'paid',
                'payment_label' => 'betaald',
                'amount' => Money::format($betalingen->sum('amount_cents')),
            ];
        }

        return [
            'payment_status' => $open->isOverdue() || $open->status->needsAttention() ? 'overdue' : 'open',
            'payment_label' => $open->status->needsAttention() ? strtolower($open->status->label()) : ($open->isOverdue() ? 'te laat' : 'openstaand'),
            'amount' => Money::format($open->amount_cents),
        ];
    }
}
