<?php

namespace App\Http\Controllers\Offerings;

use App\Actions\Offerings\PromoteParticipation;
use App\Enums\ParticipationStatus;
use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use App\Models\Participation;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        $open = $betalingen->first(fn (Payment $betaling) => $betaling->status === PaymentStatus::Open);

        if ($open === null) {
            return [
                'payment_status' => 'paid',
                'payment_label' => 'betaald',
                'amount' => Money::format($betalingen->sum('amount_cents')),
            ];
        }

        return [
            'payment_status' => $open->isOverdue() ? 'overdue' : 'open',
            'payment_label' => $open->isOverdue() ? 'te laat' : 'openstaand',
            'amount' => Money::format($open->amount_cents),
        ];
    }
}
