<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Vriendelijke herinnering bij een betaling die over de vervaldatum is.
 *
 * Toon opzettelijk zakelijk en kort: dit gaat naar ouders van kinderen, en een
 * dreigende toon over een vergeten incasso is nergens voor nodig.
 */
class BetalingHerinnering extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Payment $payment, public int $dagenTeLaat) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // In-app altijd; per mail alleen wie dat aan heeft staan.
        return $notifiable->wantsEmail('betaling')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bericht = $this->schoolMail($notifiable)
            ->subject("Herinnering: {$this->bedrag()} staat nog open")
            ->greeting('Er staat nog een betaling open')
            ->line("Het gaat om **{$this->bedrag()}** voor {$this->payment->description}.")
            ->line("De vervaldatum was {$this->payment->due_on->translatedFormat('j F Y')}, {$this->dagenTeLaat} dagen geleden.");

        return $bericht
            ->action('Naar je betalingen', route('billing.index'))
            ->line('Heb je al betaald? Dan kun je deze mail negeren; het kan een paar dagen duren voordat een overboeking is verwerkt.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'betaling_herinnering',
            'payment_id' => $this->payment->id,
            'title' => "Openstaande betaling van {$this->bedrag()}",
            'url' => '/billing',
        ];
    }

    private function bedrag(): string
    {
        return Money::format($this->payment->amount_cents);
    }
}
