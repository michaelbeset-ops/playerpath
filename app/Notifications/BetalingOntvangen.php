<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Bevestiging aan de ouders zodra een betaling binnen is. */
class BetalingOntvangen extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Betaling ontvangen')
            ->greeting('Bedankt!')
            ->line("We hebben je betaling van **{$this->bedrag()}** ontvangen.")
            ->line("Het gaat om: {$this->payment->description}.")
            ->action('Bekijk je betalingen', route('billing.index'))
            ->salutation('Met vriendelijke groet, '.config('app.name'));
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
