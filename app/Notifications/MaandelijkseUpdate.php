<?php

namespace App\Notifications;

use App\Models\Player;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * De maandelijkse update aan de ouder: dit is er veranderd.
 *
 * Toon: rustig en feitelijk. Geen superlatieven over talent, geen beloftes
 * over de toekomst. De sector wordt er publiekelijk op aangesproken dat er
 * voetbaldromen worden verkocht; deze mail moet het tegendeel laten zien -
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
            ->line("Dit is er in {$d['period']} gebeurd.");

        // Losse feiten worden één opsomming en geen rij losse alinea's: zes
        // zinnen met een witregel ertussen maakt van een korte update een lap
        // waar je doorheen moet scrollen, juist op de telefoon waarop hij
        // gelezen wordt.
        $punten = [];

        if ($d['reports'] > 0) {
            $punten[] = $d['reports'] === 1
                ? 'Eén rapport ingevuld'
                : "{$d['reports']} rapporten ingevuld";
        }

        if ($d['attended'] > 0) {
            $punten[] = $d['attended'] === 1
                ? 'Eén keer op de training geweest'
                : "{$d['attended']} keer op de training geweest";
        }

        if ($d['highlight'] !== null) {
            $punten[] = 'Grootste vooruitgang: **'.e($d['highlight']['label']).'**, nu '.$d['highlight']['now'].' op de kaart';
        } elseif ($d['delta'] !== null && $d['delta'] > 0) {
            $punten[] = "Het gemiddelde cijfer ging {$d['delta']} punten omhoog";
        }

        foreach ($d['goals'] as $doel) {
            $punten[] = $doel['status'] === 'achieved'
                ? 'Doel gehaald: '.e($doel['label']).' naar '.$doel['target']
                : 'Werkt aan: '.e($doel['label']).' naar '.$doel['target'].' - '.($doel['on_track'] ? 'op koers' : 'nog even doorzetten');
        }

        // Als HtmlString, want line() plakt gewone regels met spaties aan
        // elkaar en dan wordt de opsomming één lange zin. Alles wat van de
        // school komt is hierboven al door e() gehaald.
        if ($punten !== []) {
            $bericht->line(new HtmlString(implode("\n", array_map(fn (string $punt) => '- '.$punt, $punten))));
        }

        if ($d['nextTraining'] !== null) {
            $plek = $d['nextTraining']['location'] ? ', '.$d['nextTraining']['location'] : '';
            $bericht->line("De volgende training is op {$d['nextTraining']['date']} om {$d['nextTraining']['time']}{$plek}.");
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
