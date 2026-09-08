<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\TrainingEnrollment;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use App\Support\Payments\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De school heeft een aanvraag voor een training goedgekeurd of afgewezen.
 *
 * Bij goedkeuren staat erbij wat er nu gebeurt: betalen via een link (online),
 * contant bij de training, of niets (gratis, of op de wachtlijst omdat het
 * inmiddels vol is). Bij afwijzen staat het bericht van de school erbij; een
 * "nee" zonder waarom levert alleen een telefoontje op.
 */
class TrainingAanmeldingBeoordeeld extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(
        public TrainingEnrollment $enrollment,
        public bool $approved,
        public ?string $message,
        public ?Payment $payment,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $training = $this->enrollment->training;
        $kind = $this->enrollment->player;
        $wanneer = $training->starts_at->translatedFormat('l j F').' om '.$training->starts_at->format('H:i');

        if (! $this->approved) {
            $mail = $this->schoolMail($notifiable)
                ->subject("De aanmelding van {$kind->first_name} is niet doorgegaan")
                ->greeting('Hallo')
                ->line("De aanmelding van {$kind->first_name} voor {$training->label()} op {$wanneer} is afgewezen.");

            if ($this->message) {
                $mail->line('De school schrijft: '.$this->message);
            }

            return $mail->salutation($this->schoolSalutation($notifiable));
        }

        $mail = $this->schoolMail($notifiable)
            ->subject("{$kind->first_name} is ingeschreven voor {$training->label()}")
            ->greeting('Hallo')
            ->line("Goed nieuws: {$kind->first_name} doet mee aan {$training->label()} op {$wanneer}.");

        if ($this->enrollment->status->value === 'waitlisted') {
            return $mail
                ->line('De training zat inmiddels vol, dus '.$kind->first_name.' staat op de wachtlijst. Komt er een plek vrij, dan hoor je het.')
                ->salutation($this->schoolSalutation($notifiable));
        }

        if ($this->payment === null) {
            return $mail
                ->action('Naar de training', route('trainings.show', $training))
                ->salutation($this->schoolSalutation($notifiable));
        }

        $bedrag = Money::format($this->payment->amount_cents);

        if ($this->enrollment->paysCash()) {
            return $mail
                ->line("Je rekent {$bedrag} contant af bij de training.")
                ->action('Naar de training', route('trainings.show', $training))
                ->salutation($this->schoolSalutation($notifiable));
        }

        return $mail
            ->line("Er staat {$bedrag} klaar om te betalen.")
            ->action('Nu betalen', app(PaymentLink::class)->for($this->payment))
            ->line('De betaallink is '.PaymentLink::DAGEN_GELDIG.' dagen geldig.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $training = $this->enrollment->training;
        $kind = $this->enrollment->player;

        return [
            'type' => 'training_aanmelding',
            'training_id' => $training->id,
            'player_id' => $kind->id,
            'title' => $this->approved
                ? "{$kind->first_name} is ingeschreven voor {$training->label()} ({$training->starts_at->format('d-m')})"
                : "De aanmelding van {$kind->first_name} voor {$training->label()} is afgewezen",
            'url' => '/trainings/'.$training->id,
        ];
    }
}
