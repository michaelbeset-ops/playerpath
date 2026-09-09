<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Concerns\SendsFromSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een mededeling van de school.
 *
 * In de app krijgt iedereen hem; per mail alleen wie dat aan heeft staan. Zie
 * User::wantsEmail(): in-app meldingen zijn niet uit te zetten, want anders
 * mist iemand een afgelasting en heeft de school geen enkele manier meer om
 * hem te bereiken.
 */
class NieuweMededeling extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Announcement $announcement) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->wantsEmail('mededeling')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bericht = $this->schoolMail($notifiable)
            ->subject($this->announcement->title)
            ->greeting($this->announcement->title);

        // De tekst regel voor regel, zodat alinea's van de afzender blijven staan.
        foreach (preg_split('/\R{2,}/', trim($this->announcement->body)) ?: [] as $alinea) {
            $bericht->line($alinea);
        }

        return $bericht
            ->action('Openen in de app', route('notifications.index'))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mededeling',
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'url' => '/notifications',
        ];
    }
}
