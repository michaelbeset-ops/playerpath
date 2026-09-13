<?php

namespace App\Notifications;

use App\Models\Player;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Het seizoen is afgesloten: de eindkaart staat klaar. Naar ouders en speler. */
class SeizoenAfgesloten extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Player $player, public string $season) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsEmail('rapport')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->schoolMail($notifiable)
            ->subject("De eindkaart van {$this->player->first_name}: {$this->season}")
            ->greeting("Het seizoen {$this->season} is afgesloten")
            ->line("De kaart van {$this->player->first_name} zoals hij aan het eind van {$this->season} was, staat nu bij Mijn kaarten. Dat is de eindkaart van dit seizoen, met de cijfers en het level van toen.")
            ->line('De punten beginnen opnieuw: elke training en elk rapport telt weer mee voor de volgende kaart. De cijfers blijven staan.')
            ->action('Bekijk de kaarten', route('players.cards', $this->player))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seizoen_afgesloten',
            'player_id' => $this->player->id,
            'player_name' => $this->player->full_name,
            'title' => "De eindkaart van {$this->player->first_name} ({$this->season}) staat klaar",
            'url' => "/players/{$this->player->id}/kaarten",
        ];
    }
}
