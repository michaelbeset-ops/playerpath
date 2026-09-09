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
 * Een ouder heeft zijn kind afgemeld voor een training. De trainer hoort dat
 * meteen, met de reden als die er is, en ziet het ook in zijn
 * aanwezigheidslijst. Zo staat hij niet op het veld te wachten.
 */
class AfmeldingOntvangen extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Training $training, public Player $player, public ?string $reason) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $wanneer = $this->training->starts_at->translatedFormat('l j F').' om '.$this->training->starts_at->format('H:i');

        $bericht = $this->schoolMail($notifiable)
            ->subject("Afmelding: {$this->player->full_name} voor {$this->training->label()}")
            ->greeting("{$this->player->full_name} is afgemeld")
            ->line("Het gaat om {$this->training->label()} op {$wanneer}.");

        if ($this->reason) {
            $bericht->line('Reden: '.$this->reason);
        }

        return $bericht
            ->action('Naar de training', route('trainings.show', $this->training))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'afmelding',
            'training_id' => $this->training->id,
            'player_id' => $this->player->id,
            'title' => "{$this->player->full_name} afgemeld voor {$this->training->label()} ({$this->training->starts_at->format('d-m')})",
            'url' => '/trainings/'.$this->training->id,
        ];
    }
}
