<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\ParticipationStatus;
use App\Models\Enrollment;
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
    public function __construct(protected RemoveFromOrder $vanOrder) {}

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
                $this->vanOrder->handle($order, $enrollment);
            }
        });

        $enrollment->guardian?->notify(new InschrijvingAfgewezen($enrollment->refresh()));

        return $enrollment;
    }
}
