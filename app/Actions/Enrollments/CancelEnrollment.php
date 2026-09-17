<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\ParticipationStatus;
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
 * groep, het kind gaat van de order af (RemoveFromOrder), een abonnement
 * stopt. De restitutie rekent met het netto deel van dit kind (na korting)
 * en komt, opgeteld over de order, nooit boven wat er betaald is
 * (Order::refundableCentsFor). Wat betaald is blijft staan als betaald: dat is de
 * historie, en de restitutie staat ernaast.
 */
class CancelEnrollment
{
    public function __construct(protected RemoveFromOrder $vanOrder) {}

    public function handle(Enrollment $enrollment, User $door, ?string $reden = null): Enrollment
    {
        if (! $enrollment->status->canTransitionTo(EnrollmentStatus::Cancelled)) {
            throw new TransitionException('Deze inschrijving kan niet meer geannuleerd worden.');
        }

        $instellingen = EnrollmentSettings::for($enrollment->school);
        $beleid = RefundPolicy::for($instellingen);
        $start = $enrollment->product?->starts_on;

        DB::transaction(function () use ($enrollment, $door, $reden, $beleid, $start) {
            $order = $enrollment->order;
            $betaald = $order?->refundableCentsFor($enrollment) ?? 0;
            $restitutie = $beleid->refundCents($betaald, $start);

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

            // Dit kind van de order af: zijn regels en zijn deel van de korting,
            // en openstaande rekeningen opnieuw voor wat er nog overblijft.
            // Na de statuswissel, zodat de restitutie al meetelt.
            if ($order !== null) {
                $this->vanOrder->handle($order, $enrollment);
            }
        });

        // Na de transactie: de school hoort het (met het restitutiebedrag), en
        // de ouder krijgt een bevestiging.
        $eigenaren = User::where('school_id', $enrollment->school_id)->role(Role::Eigenaar->value)->get();
        Notification::send($eigenaren, new InschrijvingGeannuleerd($enrollment->refresh(), forSchool: true));
        $enrollment->guardian?->notify(new InschrijvingGeannuleerd($enrollment, forSchool: false));

        return $enrollment;
    }
}
