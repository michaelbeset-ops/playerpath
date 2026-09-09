<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use App\Support\Payments\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een betaling is mislukt, verlopen of gestorneerd: herinnering met een
 * nieuwe betaallink. Gaat volgens het herhaalschema van de school (bijv. na
 * 3, 7 en 14 dagen), zodat een ouder niet één keer hoort dat het misging
 * en daarna stilte.
 */
class BetalingMislukt extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Payment $payment, public int $poging, public int $totaal) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bedrag = Money::format($this->payment->amount_cents);
        $laatste = $this->poging >= $this->totaal;

        $bericht = $this->schoolMail($notifiable)
            ->subject(($laatste ? 'Laatste herinnering: ' : 'Herinnering: ')."betaling van {$bedrag} is niet gelukt")
            ->greeting('De betaling is niet gelukt')
            ->line("De betaling van **{$bedrag}** voor {$this->payment->description} is niet gelukt ({$this->payment->status->label()}).")
            ->line('Je kunt hem hieronder opnieuw doen; dat kost een halve minuut.');

        if ($laatste) {
            $bericht->line('Dit is de laatste herinnering. Blijft de betaling uit, dan neemt de school contact met je op.');
        }

        return $bericht
            ->action('Opnieuw betalen', app(PaymentLink::class)->for($this->payment))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'betaling_mislukt',
            'payment_id' => $this->payment->id,
            'title' => 'Betaling van '.Money::format($this->payment->amount_cents).' is niet gelukt',
            'url' => '/billing',
        ];
    }
}
