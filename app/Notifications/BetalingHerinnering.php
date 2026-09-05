<?php

namespace App\Notifications;

use App\Models\Payment;
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
    use Queueable;

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
        $bericht = (new MailMessage)
            ->subject('Herinnering: openstaande betaling')
            ->greeting('Hallo')
            ->line("Er staat nog een betaling van **{$this->bedrag()}** open.")
            ->line("Het gaat om: {$this->payment->description}.")
            ->line("De vervaldatum was {$this->payment->due_on->format('d-m-Y')}, {$this->dagenTeLaat} dagen geleden.");

        return $bericht
            ->action('Bekijk je betalingen', route('billing.index'))
            ->line('Heb je al betaald? Dan kun je deze mail negeren.')
            ->salutation('Met vriendelijke groet, '.config('app.name'));
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
