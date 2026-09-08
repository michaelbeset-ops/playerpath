<?php

namespace App\Notifications;

use App\Models\Player;
use App\Models\Training;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Er is een plek vrijgekomen op een training waar dit kind op de wachtlijst
 * stond. De ouder schrijft zelf alsnog in; de plek wordt niet ongevraagd
 * toegekend (zie CancelTrainingEnrollment).
 */
class PlekVrijTraining extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Training $training, public Player $player) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $wanneer = $this->training->starts_at->translatedFormat('l j F').' om '.$this->training->starts_at->format('H:i');

        return $this->schoolMail($notifiable)
            ->subject("Er is plek voor {$this->player->first_name} bij {$this->training->label()}")
            ->greeting('Hallo')
            ->line("{$this->player->first_name} stond op de wachtlijst voor {$this->training->label()} op {$wanneer}, en er is een plek vrijgekomen.")
            ->line('Wil je die plek? Schrijf dan nu in; de eerste die dat doet heeft hem.')
            ->action('Nu inschrijven', route('trainings.enroll.show', $this->training))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'plek_vrij_training',
            'training_id' => $this->training->id,
            'player_id' => $this->player->id,
            'title' => "Er is plek voor {$this->player->first_name} bij {$this->training->label()} ({$this->training->starts_at->format('d-m')})",
            'url' => '/trainings/'.$this->training->id.'/inschrijven',
        ];
    }
}
