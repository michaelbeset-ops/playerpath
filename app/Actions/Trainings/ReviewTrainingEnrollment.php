<?php

namespace App\Actions\Trainings;

use App\Enums\TrainingEnrollmentStatus;
use App\Models\TrainingEnrollment;
use App\Notifications\TrainingAanmeldingBeoordeeld;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Een aanvraag goedkeuren of afwijzen.
 *
 * Alleen bij trainingen die om goedkeuring vragen (kampen, privétrainingen,
 * een selectietraining). Goedkeuren maakt de aanmelding definitief en
 * brengt dán pas de rekening voort: betalen vóór het ja van de school zou
 * betekenen dat er geld binnenkomt van iemand die wordt afgewezen. Is de
 * training inmiddels vol, dan komt het kind op de wachtlijst in plaats van
 * dat er iemand te veel op het veld staat.
 */
class ReviewTrainingEnrollment
{
    public function __construct(protected EnrollInTraining $inschrijven) {}

    public function approve(TrainingEnrollment $aanmelding): TrainingEnrollment
    {
        $this->controleer($aanmelding);

        return DB::transaction(function () use ($aanmelding) {
            $training = $aanmelding->training;
            $vol = $training->isFull();

            $aanmelding->update([
                'status' => $vol ? TrainingEnrollmentStatus::Waitlisted : TrainingEnrollmentStatus::Confirmed,
                'note' => null,
            ]);

            $betaling = $vol ? null : $this->inschrijven->rekening($aanmelding->refresh());

            $this->bericht($aanmelding, goedgekeurd: true, bericht: null, betaling: $betaling);

            return $aanmelding->refresh();
        });
    }

    public function decline(TrainingEnrollment $aanmelding, ?string $bericht): TrainingEnrollment
    {
        $this->controleer($aanmelding);

        $aanmelding->update(['status' => TrainingEnrollmentStatus::Declined, 'note' => $bericht]);

        $this->bericht($aanmelding, goedgekeurd: false, bericht: $bericht, betaling: null);

        return $aanmelding->refresh();
    }

    protected function controleer(TrainingEnrollment $aanmelding): void
    {
        if ($aanmelding->status !== TrainingEnrollmentStatus::Requested) {
            throw new InvalidArgumentException('Deze aanmelding wacht niet (meer) op goedkeuring.');
        }
    }

    protected function bericht(TrainingEnrollment $aanmelding, bool $goedgekeurd, ?string $bericht, $betaling): void
    {
        $ouders = $aanmelding->player?->guardians ?? collect();

        if ($ouders->isEmpty()) {
            return;
        }

        Notification::send($ouders, new TrainingAanmeldingBeoordeeld($aanmelding, $goedgekeurd, $bericht, $betaling));
    }
}
