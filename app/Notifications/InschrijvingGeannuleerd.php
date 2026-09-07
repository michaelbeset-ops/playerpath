<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een inschrijving is geannuleerd. De school hoort het met het bedrag dat
 * volgens haar beleid terug moet; de ouder krijgt een bevestiging met
 * hetzelfde bedrag, zodat beide partijen dezelfde afspraak in handen hebben.
 */
class InschrijvingGeannuleerd extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Enrollment $enrollment, public bool $forSchool) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kind = $this->enrollment->first_name;
        $aanbod = $this->enrollment->product?->name ?? 'het aanbod';
        $restitutie = (int) ($this->enrollment->refund_cents ?? 0);

        if ($this->forSchool) {
            $bericht = $this->schoolMail($notifiable)
                ->subject("Annulering: {$kind} voor {$aanbod}")
                ->greeting('Hallo')
                ->line("{$this->enrollment->guardian_name} heeft de inschrijving van {$kind} voor {$aanbod} geannuleerd.");

            if ($this->enrollment->cancellation_reason) {
                $bericht->line('Reden: '.$this->enrollment->cancellation_reason);
            }

            return $bericht
                ->line($restitutie > 0
                    ? 'Volgens je restitutiebeleid komt er **'.Money::format($restitutie).'** terug. Dat betaal je zelf terug; de app boekt niets.'
                    : 'Volgens je restitutiebeleid komt er niets terug.')
                ->action('Naar de inschrijvingen', route('enrollments.index'))
                ->salutation($this->schoolSalutation($notifiable));
        }

        return $this->schoolMail($notifiable)
            ->subject("De inschrijving van {$kind} is geannuleerd")
            ->greeting('Hallo')
            ->line("De inschrijving van {$kind} voor {$aanbod} is geannuleerd.")
            ->line($restitutie > 0
                ? 'Je krijgt **'.Money::format($restitutie).'** terug. De school regelt dat met je.'
                : 'Er komt volgens de voorwaarden van de school niets terug.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inschrijving_geannuleerd',
            'enrollment_id' => $this->enrollment->id,
            'title' => "Inschrijving van {$this->enrollment->first_name} geannuleerd",
            'url' => $this->forSchool ? '/enrollments' : '/billing',
        ];
    }
}
