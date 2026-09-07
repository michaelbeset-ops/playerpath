<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\User;
use App\Notifications\InschrijvingGoedgekeurd;
use App\Support\Status\TransitionException;
use RuntimeException;

/**
 * Een inschrijving goedkeuren (school met handmatig goedkeuren).
 *
 * De speler en het ouderaccount bestaan al sinds het indienen. Goedkeuren
 * betekent: de order gaat open en de ouder krijgt een betaalverzoek, of — als
 * er niets te betalen valt — de inschrijving is meteen rond.
 *
 * Betalen gebeurt dus pas ná goedkeuring. Anders kan er geld binnenkomen van
 * iemand die de school afwijst, en terugbetalen is een gesprek, geen knop.
 */
class ApproveEnrollment
{
    public function __construct(protected ConfirmEnrollment $bevestig, protected InviteFromWaitlist $uitnodigen) {}

    public function handle(Enrollment $enrollment, User $eigenaar): Player
    {
        if (! in_array($enrollment->status, [EnrollmentStatus::AwaitingApproval, EnrollmentStatus::Waitlist], strict: true)) {
            throw new RuntimeException('Deze inschrijving is al afgehandeld.');
        }

        if ($enrollment->player === null) {
            throw new RuntimeException('Bij deze inschrijving hoort geen speler meer.');
        }

        // Vanaf de wachtlijst: een uitnodiging met betaallink en tijdslimiet.
        // Op de wachtlijst stond niets open; de order ontstaat daar pas.
        if ($enrollment->status === EnrollmentStatus::Waitlist) {
            $this->uitnodigen->handle($enrollment, $eigenaar);

            return $enrollment->player;
        }

        $enrollment->forceFill([
            'handled_by_id' => $eigenaar->id,
            'handled_at' => now(),
        ])->save();

        try {
            $this->bevestig->openOrConfirm($enrollment, $enrollment->order);
        } catch (TransitionException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        // Wacht op betaling: dan is het bericht "goedgekeurd, hier is je
        // betaallink". Bevestigd zonder bedrag heeft ConfirmEnrollment al gemeld.
        if ($enrollment->refresh()->status === EnrollmentStatus::AwaitingPayment && $enrollment->guardian !== null) {
            $rekening = $enrollment->order?->payments()->orderBy('due_on')->orderBy('id')->first();
            $enrollment->guardian->notify(new InschrijvingGoedgekeurd($enrollment->player, $rekening, false));
        }

        return $enrollment->player;
    }
}
