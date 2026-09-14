<?php

namespace App\Notifications;

use App\Models\Player;
use App\Models\Report;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Rating\Grade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Er is een nieuw rapport van je kind."
 *
 * Gaat naar de ouders van de speler en naar de speler zelf, als die een eigen
 * account heeft. In de app én per e-mail, en altijd via de queue: het opslaan
 * van een rapport mag nooit wachten op een mailserver - dat scherm moet in
 * dertig seconden klaar zijn.
 */
class NieuwRapport extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(
        public Report $report,
        public Player $player,
        public ?int $overallRating,
        public ?int $groei,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // In-app altijd; per mail alleen wie dat aan heeft staan.
        return $notifiable->wantsEmail('rapport')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $naam = $this->player->first_name;

        $bericht = $this->schoolMail($notifiable)
            ->subject("Nieuw rapport voor {$naam}")
            ->greeting("Nieuw rapport voor {$naam}")
            ->line('De trainer heeft na de training zijn beoordeling ingevuld.');

        // In kleuren: geen getal in de mail, alleen de kleur. Een getal in een
        // inbox is precies wat een school die in kleuren werkt niet wil.
        if (Grade::usesColors($this->player->school)) {
            if ($this->overallRating !== null) {
                $bericht->line('De spelerskaart laat nu zien: **'.Grade::labelFor($this->overallRating).'**.');
            }

            if ($this->groei !== null && $this->groei > 0) {
                $bericht->line('Er is groei te zien ten opzichte van het vorige rapport. Mooi bezig!');
            }
        } else {
            if ($this->overallRating !== null) {
                $bericht->line("De spelerskaart staat nu op **{$this->overallRating}**.");
            }

            if ($this->groei !== null && $this->groei > 0) {
                $bericht->line("Dat is {$this->groei} punten hoger dan het vorige rapport. Mooi bezig!");
            }
        }

        return $bericht
            ->action('Bekijk de spelerskaart', route('players.card', $this->player))
            ->line('Je krijgt deze mail omdat je aan deze speler gekoppeld bent. In je account stel je in welke mail je wilt ontvangen.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'nieuw_rapport',
            'player_id' => $this->player->id,
            'player_name' => $this->player->full_name,
            'report_id' => $this->report->id,
            // In kleuren geen getal in de melding: de lijst toont dan het label.
            'overall_rating' => Grade::usesColors($this->player->school) ? null : $this->overallRating,
            'groei' => Grade::usesColors($this->player->school) ? null : $this->groei,
            'grade' => Grade::usesColors($this->player->school) ? Grade::labelFor($this->overallRating) : null,
            'title' => "Nieuw rapport voor {$this->player->first_name}",
            'url' => "/players/{$this->player->id}/card",
        ];
    }
}
