<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een blok loopt af en verlengt niet vanzelf: de ouder krijgt vóór het einde
 * een uitnodiging om opnieuw aan te melden. De knop wijst naar de
 * inschrijfpagina; ingelogd staan naam en kind daar al klaar.
 */
class VerlengUitnodiging extends Notification implements ShouldQueue
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
        $aanbod = $this->enrollment->product;
        $einde = $aanbod?->ends_on?->translatedFormat('j F');

        return $this->schoolMail($notifiable)
            ->subject("{$aanbod?->name} loopt af. Gaat {$this->enrollment->first_name} door?")
            ->greeting('Hallo')
            ->line("{$aanbod?->name} van {$this->enrollment->first_name} loopt op {$einde} af.")
            ->line('Wil je doorgaan? Meld dan opnieuw aan; je plek is niet automatisch verlengd.')
            ->action('Opnieuw aanmelden', route('enroll.show', $this->enrollment->school))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'verlengen',
            'enrollment_id' => $this->enrollment->id,
            'title' => "{$this->enrollment->product?->name} van {$this->enrollment->first_name} loopt af",
            'url' => route('enroll.show', $this->enrollment->school, absolute: false),
        ];
    }
}
