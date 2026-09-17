<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De school heeft een aanmelding afgewezen.
 *
 * Rustig en feitelijk: wat er gebeurd is, dat er niets betaald hoeft te
 * worden, en bij wie je terechtkunt. Geen reden erbij verzinnen - die kent
 * alleen de school, en die kan de ouder bellen.
 */
class InschrijvingAfgewezen extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Enrollment $enrollment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Een antwoord op een aanmelding; geen nieuwsbrief om je voor af te melden.
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kind = $this->enrollment->first_name;
        $aanbod = $this->enrollment->product?->name ?? 'het aanbod';

        return $this->schoolMail($notifiable)
            ->subject("De aanmelding van {$kind} is niet doorgegaan")
            ->greeting("De aanmelding van {$kind} is niet doorgegaan")
            ->line("De school heeft de aanmelding voor {$aanbod} niet kunnen goedkeuren.")
            ->line('Voor deze aanmelding hoef je niets te betalen.')
            ->line('Heb je vragen, of wil je weten wat er wel mogelijk is? Neem dan contact op met de school.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inschrijving_afgewezen',
            'enrollment_id' => $this->enrollment->id,
            'title' => "Aanmelding van {$this->enrollment->first_name} niet doorgegaan",
            'url' => '/billing',
        ];
    }
}
