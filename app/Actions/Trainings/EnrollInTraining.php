<?php

namespace App\Actions\Trainings;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TrainingEnrollmentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Een kind los inschrijven op één training.
 *
 * De regels van de training bepalen wat er ontstaat:
 *
 * - past het kind niet (leeftijd, positie) of is de training dicht, dan
 *   gebeurt er niets - dat is de **server-side** controle; het scherm
 *   verbergt alleen wat toch niet kan;
 * - is het vol, dan komt het kind op de **wachtlijst**, zonder rekening;
 * - vraagt de training om goedkeuring, dan is het een **aanvraag**, ook
 *   zonder rekening: betalen komt pas na het ja van de school;
 * - anders is het meteen **ingeschreven**, met een rekening als het iets kost.
 *
 * De rekening is een gewone Payment, zodat het financiële overzicht hem
 * telt. Contant betekent: openstaand tot de trainer bij de training afvinkt
 * dat het geld er is. Dat is geen achterstand; zie Payment::isCashAtTraining().
 */
class EnrollInTraining
{
    public function handle(Training $training, Player $player, ?User $by, ?string $paymentMethod): TrainingEnrollment
    {
        if (! $training->isOpenForEnrollment()) {
            throw new InvalidArgumentException('Op deze training kun je niet (meer) inschrijven.');
        }

        if (! $training->acceptsPlayer($player)) {
            throw new InvalidArgumentException("{$player->first_name} valt buiten de leeftijd of positie van deze training.");
        }

        if ($training->group_id !== null && $training->group->players()->whereKey($player->id)->exists()) {
            throw new InvalidArgumentException("{$player->first_name} zit al in de groep van deze training.");
        }

        if ($training->price_cents > 0 && $paymentMethod !== null && ! $training->allowsPayment($paymentMethod)) {
            throw new InvalidArgumentException('Deze betaalwijze kan niet bij deze training.');
        }

        return DB::transaction(function () use ($training, $player, $by, $paymentMethod) {
            $aanmelding = TrainingEnrollment::firstOrNew([
                'training_id' => $training->id,
                'player_id' => $player->id,
            ]);

            if ($aanmelding->exists && in_array($aanmelding->status, [TrainingEnrollmentStatus::Requested, TrainingEnrollmentStatus::Confirmed], true)) {
                throw new InvalidArgumentException("{$player->first_name} is al aangemeld voor deze training.");
            }

            $status = match (true) {
                $training->isFull() => TrainingEnrollmentStatus::Waitlisted,
                $training->requires_approval => TrainingEnrollmentStatus::Requested,
                default => TrainingEnrollmentStatus::Confirmed,
            };

            $aanmelding->fill([
                'user_id' => $by?->id,
                'status' => $status,
                'payment_method' => $training->price_cents > 0 ? $paymentMethod : null,
                'note' => null,
            ])->save();

            if ($status === TrainingEnrollmentStatus::Confirmed) {
                $this->rekening($aanmelding);
            }

            return $aanmelding->refresh();
        });
    }

    /**
     * De rekening bij een bevestigde aanmelding, als de training iets kost.
     *
     * Ook aangeroepen bij goedkeuren. Idempotent: een tweede keer levert geen
     * tweede rekening op.
     */
    public function rekening(TrainingEnrollment $aanmelding): ?Payment
    {
        $training = $aanmelding->training;

        if ($training->price_cents <= 0) {
            return null;
        }

        $bestaand = $aanmelding->payment()->first();

        if ($bestaand !== null && $bestaand->status !== PaymentStatus::Cancelled) {
            return $bestaand;
        }

        return Payment::create([
            'player_id' => $aanmelding->player_id,
            'training_enrollment_id' => $aanmelding->id,
            'amount_cents' => $training->price_cents,
            'vat_rate' => 0,
            'status' => PaymentStatus::Open,
            // Contant is contant; online wordt pas ingevuld door de provider.
            'method' => $aanmelding->paysCash() ? PaymentMethod::Cash : null,
            'description' => 'Training '.$training->label().' op '.$training->starts_at->translatedFormat('j F'),
            'due_on' => $training->starts_at->toDateString(),
        ]);
    }
}
