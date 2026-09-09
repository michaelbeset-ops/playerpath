<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Er is een nieuwe inschrijving." Naar de eigenaar, in de app en per mail.
 */
class NieuweInschrijving extends Notification implements ShouldQueue
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
        return $this->schoolMail($notifiable)
            ->subject("Nieuwe inschrijving: {$this->enrollment->child_name}")
            ->greeting("Nieuwe inschrijving: {$this->enrollment->child_name}")
            ->line("{$this->enrollment->guardian_name} heeft {$this->enrollment->child_name} ({$this->enrollment->position->label()}, {$this->enrollment->age} jaar) ingeschreven.")
            ->line($this->enrollment->product ? "Gewenst tarief: {$this->enrollment->product->name}." : 'Er is nog geen tarief gekozen.')
            ->action('Bekijk de inschrijving', route('enrollments.index'))
            ->line('Zolang je hem niet goedkeurt is er niets betaald en staat het kind niet in een groep.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'nieuwe_inschrijving',
            'enrollment_id' => $this->enrollment->id,
            'title' => "Nieuwe inschrijving: {$this->enrollment->child_name}",
            'url' => '/enrollments',
        ];
    }
}
