<?php

namespace App\Actions\Trainings;

use App\Enums\PaymentStatus;
use App\Enums\TrainingEnrollmentStatus;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Notifications\PlekVrijTraining;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Een losse aanmelding afmelden, en de plek doorgeven.
 *
 * De rekening die nog openstond vervalt; wat al betaald was blijft betaald —
 * of er iets terug gaat is een gesprek tussen school en ouder, geen boeking.
 *
 * Komt er zo een plek vrij, dan krijgt de **eerste op de wachtlijst** bericht
 * met de mogelijkheid alsnog in te schrijven. Bewust geen automatische
 * bevestiging: die ouder heeft misschien allang iets anders geregeld, en een
 * rekening voor een training waar niemand meer op rekende is erger dan een
 * berichtje.
 */
class CancelTrainingEnrollment
{
    public function handle(TrainingEnrollment $aanmelding): void
    {
        DB::transaction(function () use ($aanmelding) {
            $namPlekIn = $aanmelding->status->takesSpot();

            $aanmelding->update(['status' => TrainingEnrollmentStatus::Cancelled]);

            $aanmelding->payment()
                ->where('status', PaymentStatus::Open->value)
                ->update(['status' => PaymentStatus::Cancelled->value]);

            if ($namPlekIn) {
                $this->geefPlekDoor($aanmelding->training);
            }
        });
    }

    /**
     * De eerste op de wachtlijst die nog geen bericht had.
     *
     * Eén tegelijk: twee gezinnen voor één plek uitnodigen is precies de
     * teleurstelling die een wachtlijst hoort te voorkomen.
     */
    public function geefPlekDoor(Training $training): void
    {
        if ($training->isFull()) {
            return;
        }

        $volgende = $training->enrollments()
            ->waitlisted()
            ->whereNull('invited_at')
            ->with('player.guardians')
            ->first();

        if ($volgende === null) {
            return;
        }

        $volgende->update(['invited_at' => now()]);

        $ouders = $volgende->player?->guardians ?? collect();

        if ($ouders->isNotEmpty()) {
            Notification::send($ouders, new PlekVrijTraining($training, $volgende->player));
        }
    }
}
