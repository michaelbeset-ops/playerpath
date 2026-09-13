<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Bevestiging aan de ouders zodra een betaling binnen is. */
class BetalingOntvangen extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Payment $payment) {}

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
        return $this->schoolMail($notifiable)
            ->subject("Betaling van {$this->bedrag()} ontvangen")
            ->greeting('Je betaling is binnen')
            ->line("Bedankt - we hebben **{$this->bedrag()}** ontvangen voor {$this->payment->description}.")
            ->line('Je hoeft verder niets te doen.')
            ->action('Naar je betalingen', route('billing.index'))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'betaling_ontvangen',
            'payment_id' => $this->payment->id,
            'title' => "Betaling van {$this->bedrag()} ontvangen",
            'url' => '/billing',
        ];
    }

    private function bedrag(): string
    {
        return Money::format($this->payment->amount_cents);
    }
}
