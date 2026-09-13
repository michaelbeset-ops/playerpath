<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\OrderLineType;
use App\Enums\OrderStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\InschrijvingGeannuleerd;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Enrollment\RefundPolicy;
use App\Support\Status\TransitionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Een inschrijving annuleren, door de ouder of door de school.
 *
 * Wat er terugkomt volgt het restitutiebeleid uit de instellingen
 * (RefundPolicy): kosteloos tot X dagen voor de start, daarna Y% ingehouden.
 * Het bedrag wordt vastgelegd op de inschrijving (`refund_cents`); het
 * terugbetalen zelf is een handeling van de school - er is geen automatische
 * boeking, want terugbetalen zit niet in de app zolang er geen provider is.
 *
 * Wat er verder gebeurt: de deelname gaat op geannuleerd en het kind uit de
 * groep, openstaande rekeningen van deze inschrijving worden geannuleerd, een
 * abonnement stopt. Wat betaald is blijft staan als betaald: dat is de
 * historie, en de restitutie staat ernaast.
 */
class CancelEnrollment
{
    public function handle(Enrollment $enrollment, User $door, ?string $reden = null): Enrollment
    {
        if (! $enrollment->status->canTransitionTo(EnrollmentStatus::Cancelled)) {
            throw new TransitionException('Deze inschrijving kan niet meer geannuleerd worden.');
        }

        $instellingen = EnrollmentSettings::for($enrollment->school);
        $beleid = RefundPolicy::for($instellingen);
        $start = $enrollment->product?->starts_on;

        DB::transaction(function () use ($enrollment, $door, $reden, $beleid, $start) {
            $betaald = $this->betaaldVoor($enrollment);
            $restitutie = $beleid->refundCents($betaald, $start);

            // Openstaande rekeningen van deze order vervallen, tenzij een ander
            // kind op dezelfde order nog gewoon meedoet.
            $order = $enrollment->order;

            if ($order !== null) {
                $anderen = $order->enrollments()->whereKeyNot($enrollment->id)->get()
                    ->filter(fn (Enrollment $e) => $e->status->isSettled() || $e->status->isOpen());

                if ($anderen->isEmpty()) {
                    $order->payments()->outstanding()->update(['status' => PaymentStatus::Cancelled->value]);
                    $order->forceFill(['status' => $order->paid_at ? OrderStatus::Paid : OrderStatus::Cancelled])->save();
                }
            }

            // De deelname en de groep.
            $deelname = $enrollment->player?->participations()->where('product_id', $enrollment->product_id)->first();

            if ($deelname !== null) {
                $deelname->update(['status' => ParticipationStatus::Cancelled]);

                if ($enrollment->product?->group !== null) {
                    $enrollment->player->groups()->detach($enrollment->product->group->id);
                }

                // Een abonnement uit deze inschrijving stopt per direct.
                $deelname->subscription?->transitionTo(SubscriptionStatus::Ended, ['ends_on' => now()->toDateString()]);
            }

            $enrollment->transitionTo(EnrollmentStatus::Cancelled, [
                'cancelled_at' => now(),
                'cancellation_reason' => $reden,
                'refund_cents' => $restitutie,
                'handled_by_id' => $door->isEigenaar() ? $door->id : $enrollment->handled_by_id,
            ]);
        });

        // Na de transactie: de school hoort het (met het restitutiebedrag), en
        // de ouder krijgt een bevestiging.
        $eigenaren = User::where('school_id', $enrollment->school_id)->role(Role::Eigenaar->value)->get();
        Notification::send($eigenaren, new InschrijvingGeannuleerd($enrollment->refresh(), forSchool: true));
        $enrollment->guardian?->notify(new InschrijvingGeannuleerd($enrollment, forSchool: false));

        return $enrollment;
    }

    /** Wat er voor deze inschrijving betaald is: het aanbodbedrag, voor zover de order betaald is. */
    protected function betaaldVoor(Enrollment $enrollment): int
    {
        $order = $enrollment->order;

        if ($order === null) {
            return 0;
        }

        $regel = (int) $order->lines()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('type', [OrderLineType::Offering->value, OrderLineType::Trial->value])
            ->sum('amount_cents');

        $betaald = (int) $order->payments()->get()->filter(fn ($p) => $p->status->countsAsRevenue())->sum('amount_cents');

        return max(0, min($regel, $betaald));
    }
}
