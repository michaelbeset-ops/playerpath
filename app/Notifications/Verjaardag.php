<?php

namespace App\Notifications;

use App\Models\Player;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Gefeliciteerd!"
 *
 * Gaat naar de speler zelf als die een eigen account heeft, en anders naar de
 * ouders. Een school die op de verjaardag van een kind iets van zich laat
 * horen doet iets wat een ouder onthoudt; dat is precies het soort ding dat
 * niemand handmatig volhoudt.
 *
 * De school kan er een eigen zin bij zetten. Doet ze dat niet, dan staat er
 * een nette standaardtekst — een lege felicitatie is erger dan geen.
 */
class Verjaardag extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(
        public Player $player,
        public int $leeftijd,
        public ?string $bericht = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // In-app altijd; per mail alleen wie dat aan heeft staan.
        return $notifiable->wantsEmail('verjaardag')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $naam = $this->player->first_name;

        return $this->schoolMail($notifiable)
            ->subject("Gefeliciteerd, {$naam}!")
            ->greeting("Gefeliciteerd, {$naam}!")
            ->line($this->bericht ?: "{$naam} is vandaag {$this->leeftijd} geworden. Van harte gefeliciteerd namens de hele school!")
            ->line('Tot op de training.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'verjaardag',
            'title' => "{$this->player->first_name} is jarig",
            'body' => "{$this->player->first_name} wordt vandaag {$this->leeftijd}.",
            'url' => '/players/'.$this->player->id.'/card',
            'player_id' => $this->player->id,
        ];
    }
}
