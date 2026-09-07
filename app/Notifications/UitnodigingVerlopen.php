<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De uitnodiging vanaf de wachtlijst is verlopen: de plek is naar de volgende
 * gegaan. Eerlijk zeggen, in plaats van een betaallink die stilletjes niet
 * meer werkt.
 */
class UitnodigingVerlopen extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Enrollment $enrollment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $aanbod = $this->enrollment->product?->name ?? 'het aanbod';

        return $this->schoolMail($notifiable)
            ->subject("De plek voor {$this->enrollment->first_name} bij {$aanbod} is vervallen")
            ->greeting('Hallo')
            ->line("We hadden een plek voor {$this->enrollment->first_name} bij {$aanbod}, maar de betaallink is verlopen. De plek is naar de volgende op de wachtlijst gegaan.")
            ->line('Wil je alsnog meedoen? Meld je dan opnieuw aan, of neem contact op met de school.')
            ->action('Opnieuw aanmelden', route('enroll.show', $this->enrollment->school))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'uitnodiging_verlopen',
            'enrollment_id' => $this->enrollment->id,
            'title' => "De plek voor {$this->enrollment->first_name} is vervallen",
            'url' => route('enroll.show', $this->enrollment->school, absolute: false),
        ];
    }
}
