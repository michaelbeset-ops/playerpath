<?php

namespace App\Notifications;

use App\Models\Player;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De maandelijkse update aan de ouder: dit is er veranderd.
 *
 * Toon: rustig en feitelijk. Geen superlatieven over talent, geen beloftes
 * over de toekomst. De sector wordt er publiekelijk op aangesproken dat er
 * voetbaldromen worden verkocht; deze mail moet het tegendeel laten zien —
 * gewoon wat er die maand gebeurd is.
 */
class MaandelijkseUpdate extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    /** @param array<string, mixed> $digest */
    public function __construct(public Player $player, public array $digest) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsEmail('samenvatting')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $naam = $this->player->first_name;
        $d = $this->digest;

        $bericht = $this->schoolMail($notifiable)
            ->subject("Hoe het met {$naam} gaat")
            ->greeting("De maand van {$naam}")
            ->line("Periode: {$d['period']}.");

        if ($d['reports'] > 0) {
            $bericht->line($d['reports'] === 1
                ? 'Er is één rapport ingevuld.'
                : "Er zijn {$d['reports']} rapporten ingevuld.");
        }

        if ($d['attended'] > 0) {
            $bericht->line($d['attended'] === 1
                ? "{$naam} was één keer op de training."
                : "{$naam} was {$d['attended']} keer op de training.");
        }

        if ($d['highlight'] !== null) {
            $bericht->line("Grootste vooruitgang: **{$d['highlight']['label']}**, nu {$d['highlight']['now']} op de kaart.");
        } elseif ($d['delta'] !== null && $d['delta'] > 0) {
            $bericht->line("Het gemiddelde cijfer ging deze maand {$d['delta']} punten omhoog.");
        }

        foreach ($d['goals'] as $doel) {
            $bericht->line($doel['status'] === 'achieved'
                ? "Doel gehaald: {$doel['label']} naar {$doel['target']}."
                : "Werkt aan: {$doel['label']} naar {$doel['target']} — ".($doel['on_track'] ? 'op koers' : 'nog even doorzetten').'.');
        }

        if ($d['nextTraining'] !== null) {
            $plek = $d['nextTraining']['location'] ? ', '.$d['nextTraining']['location'] : '';
            $bericht->line("Volgende training: {$d['nextTraining']['date']} om {$d['nextTraining']['time']}{$plek}.");
        }

        return $bericht
            ->action('Bekijk de kaart', route('players.card', $this->player))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'maandupdate',
            'player_id' => $this->player->id,
            'player_name' => $this->player->full_name,
            'title' => "De maand van {$this->player->first_name}",
            'body' => $this->samenvatting(),
            'url' => "/players/{$this->player->id}/card",
        ];
    }

    private function samenvatting(): string
    {
        $d = $this->digest;
        $delen = [];

        if ($d['reports'] > 0) {
            $delen[] = $d['reports'].' '.($d['reports'] === 1 ? 'rapport' : 'rapporten');
        }

        if ($d['attended'] > 0) {
            $delen[] = $d['attended'].'x aanwezig';
        }

        if ($d['highlight'] !== null) {
            $delen[] = 'vooruit op '.strtolower($d['highlight']['label']);
        }

        return $delen === [] ? $d['period'] : implode(' · ', $delen);
    }
}
