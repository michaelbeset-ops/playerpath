<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De vooraankondiging van een incasso: wat er wordt afgeschreven, waarvoor,
 * en rond wanneer. Minstens veertien dagen vooraf, zodat een ouder kan
 * zorgen dat het er staat - of aan de bel kan trekken als het niet klopt.
 */
class IncassoAankondiging extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(public Payment $payment, public CarbonInterface $collectsOn) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Dit is geen nieuwsbrief: een afschrijving aankondigen is verplicht.
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bedrag = Money::format($this->payment->amount_cents);

        return $this->schoolMail($notifiable)
            ->subject("Vooraankondiging: {$bedrag} wordt rond {$this->collectsOn->translatedFormat('j F')} afgeschreven")
            ->greeting('Vooraankondiging van een incasso')
            ->line("Rond **{$this->collectsOn->translatedFormat('j F Y')}** schrijven we **{$bedrag}** af van je rekening voor {$this->payment->description}.")
            ->line('Zorg dat het bedrag er rond die dag op staat. Klopt er iets niet? Neem dan vóór die datum contact met ons op.')
            ->action('Naar je betalingen', route('billing.index'))
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'incasso_aankondiging',
            'payment_id' => $this->payment->id,
            'title' => Money::format($this->payment->amount_cents).' wordt rond '.$this->collectsOn->format('d-m-Y').' afgeschreven',
            'url' => '/billing',
        ];
    }
}
