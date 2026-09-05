<?php

namespace App\Notifications;

use App\Models\Goal;
use App\Models\Player;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Doel gehaald!" Naar de ouders en de speler zelf. */
class DoelBehaald extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Goal $goal, public Player $player) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // In-app altijd; per mail alleen wie dat aan heeft staan.
        return $notifiable->wantsEmail('doel')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->schoolMail($notifiable)
            ->subject("{$this->player->first_name} heeft een doel gehaald!")
            ->greeting('Goed nieuws!')
            ->line("{$this->player->first_name} heeft het doel **{$this->goal->category->label()} naar {$this->goal->target_rating}** gehaald.")
            ->line('Dat staat nu als mijlpaal op de spelerskaart.')
            ->action('Bekijk de spelerskaart', route('players.card', $this->player))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'doel_behaald',
            'player_id' => $this->player->id,
            'player_name' => $this->player->full_name,
            'title' => "{$this->player->first_name} heeft een doel gehaald: {$this->goal->category->label()} naar {$this->goal->target_rating}",
            'url' => "/players/{$this->player->id}/card",
        ];
    }
}
