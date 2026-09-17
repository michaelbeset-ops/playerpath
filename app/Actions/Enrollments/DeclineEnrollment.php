<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\OrderLineType;
use App\Enums\OrderStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\InschrijvingAfgewezen;
use App\Support\Status\TransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Een aanmelding afwijzen (door de school).
 *
 * Eén order kan meerdere kinderen bundelen. Afwijzen raakt daarom alleen dít
 * kind: zijn regels gaan van de order af en het totaal wordt opnieuw
 * berekend (Order::recalculate). Blijft er geen ander kind over dat nog
 * meedoet of wacht, dan gaat de hele order dicht - ook de regels per gezin
 * (inschrijfgeld, kledingpakket) hebben dan geen reden meer.
 *
 * Staan er al rekeningen open die op het oude totaal gebaseerd zijn, dan
 * vervallen die en worden ze opnieuw opgemaakt voor wat er overblijft. Wat
 * al betaald is blijft staan: dat is de historie, en terugbetalen is een
 * gesprek tussen school en ouder, geen boeking.
 *
 * Een plek op de wachtlijst wordt geannuleerd, zodat het aanbodbeheer hem
 * niet meer als wachtende telt. De ouder krijgt bericht, na de transactie.
 */
class DeclineEnrollment
{
    public function __construct(protected ConfirmEnrollment $bevestig) {}

    public function handle(Enrollment $enrollment, User $door): Enrollment
    {
        if (! $enrollment->status->canTransitionTo(EnrollmentStatus::Declined)) {
            throw new TransitionException('Deze aanmelding kan niet meer worden afgewezen.');
        }

        DB::transaction(function () use ($enrollment, $door) {
            $order = $enrollment->order;

            // De wachtlijstplek (of een deelname die er al stond) vervalt.
            $enrollment->player?->participations()
                ->where('product_id', $enrollment->product_id)
                ->where('status', ParticipationStatus::Waitlist->value)
                ->update(['status' => ParticipationStatus::Cancelled->value]);

            $enrollment->transitionTo(EnrollmentStatus::Declined, [
                'handled_by_id' => $door->id,
                'handled_at' => now(),
                // Los van de order: die gaat verder over de andere kinderen.
                'order_id' => null,
            ]);

            if ($order !== null) {
                $this->werkOrderBij($order, $enrollment);
            }
        });

        $enrollment->guardian?->notify(new InschrijvingAfgewezen($enrollment->refresh()));

        return $enrollment;
    }

    protected function werkOrderBij(Order $order, Enrollment $enrollment): void
    {
        $order->lines()->where('enrollment_id', $enrollment->id)->delete();

        // Een gezinskorting die aan dit kind hing (op speler, zonder inschrijving).
        if ($enrollment->player_id !== null) {
            $order->lines()
                ->whereNull('enrollment_id')
                ->where('player_id', $enrollment->player_id)
                ->where('type', OrderLineType::Discount->value)
                ->delete();
        }

        $anderen = $order->enrollments()->get()
            ->filter(fn (Enrollment $e) => $e->status->isOpen() || $e->status->isSettled());

        $openstaand = $order->payments()->outstanding()->get()
            ->filter(fn (Payment $p) => $p->canTransitionTo(PaymentStatus::Cancelled));
        $betaald = $order->payments()->get()->contains(fn (Payment $p) => $p->status->countsAsRevenue());

        if ($anderen->isEmpty()) {
            $openstaand->each(fn (Payment $p) => $p->transitionTo(PaymentStatus::Cancelled));
            $order->lines()->delete();
            $order->recalculate();
            $order->forceFill(['status' => $betaald ? OrderStatus::Paid : OrderStatus::Cancelled])->save();

            return;
        }

        $order->recalculate();

        // Rekeningen op het oude totaal kloppen niet meer. Alleen opnieuw
        // opmaken als er nog niets betaald is; anders zou een deelbetaling
        // dubbel meetellen, en dat hoort bij de school.
        if ($order->status === OrderStatus::Open && $openstaand->isNotEmpty() && ! $betaald) {
            $openstaand->each(fn (Payment $p) => $p->transitionTo(PaymentStatus::Cancelled));

            if ($order->total_cents > 0) {
                $this->bevestig->maakRekeningen($order);
            }
        }
    }
}
