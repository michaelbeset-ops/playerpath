<?php

namespace App\Notifications;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Order;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use App\Support\Payments\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De bevestiging aan de ouder direct na het indienen: wat er is aangemeld,
 * wat er nu gebeurt (wachten op de school, betalen, of rond) en — bij een
 * nieuw account — dat hij kan inloggen met het wachtwoord dat hij koos.
 */
class InschrijvingOntvangen extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    /** @param  list<Enrollment>  $enrollments */
    public function __construct(
        public array $enrollments,
        public ?Order $order,
        public bool $newAccount,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $namen = collect($this->enrollments)->pluck('first_name')->join(', ', ' en ');
        $eerste = $this->enrollments[0];

        $bericht = $this->schoolMail($notifiable)
            ->subject("We hebben de aanmelding van {$namen} ontvangen")
            ->greeting('We hebben je aanmelding ontvangen')
            ->line("Bedankt voor het aanmelden van {$namen}".($eerste->product ? " voor {$eerste->product->name}" : '').'.');

        $bericht = match ($eerste->status) {
            EnrollmentStatus::Waitlist => $bericht
                ->line('Dit aanbod zit vol. Je staat op de wachtlijst; zodra er plek is hoor je het, en je betaalt pas dan.'),
            EnrollmentStatus::AwaitingApproval => $bericht
                ->line('De school bekijkt je aanmelding. Zodra die is goedgekeurd krijg je bericht, met daarin hoe je betaalt.'),
            EnrollmentStatus::AwaitingPayment => $this->betaalregel($bericht),
            default => $bericht->line('De inschrijving is rond. Welkom!'),
        };

        if ($this->newAccount) {
            // Eén knop per mail. Staat er al een betaalknop, dan wordt inloggen
            // een zin: een tweede ->action() overschrijft de eerste, en dan is
            // precies de knop weg waar deze mail voor bedoeld was.
            $bericht->line($bericht->actionText === null
                ? 'Er is een account voor je aangemaakt. Je logt in met dit e-mailadres en het wachtwoord dat je koos; daar zie je de trainingen, de spelerskaart en wat er openstaat.'
                : 'Er is ook een account voor je aangemaakt: je logt in met dit e-mailadres en het wachtwoord dat je koos.');

            if ($bericht->actionText === null) {
                $bericht->action('Inloggen', route('login'));
            }
        }

        return $bericht->salutation($this->schoolSalutation($notifiable));
    }

    protected function betaalregel(MailMessage $bericht): MailMessage
    {
        $rekening = $this->order?->payments()->orderBy('due_on')->orderBy('id')->first();

        if ($rekening === null) {
            return $bericht->line('Er valt niets te betalen; de inschrijving is rond.');
        }

        $bedrag = Money::format($rekening->amount_cents);

        if ($rekening->method?->isOffline()) {
            return $bericht->line("Er staat **{$bedrag}** open. Dat reken je af bij de school; daarna is de inschrijving rond.");
        }

        return $bericht
            ->line("Er staat **{$bedrag}** open. Zodra dat betaald is, is de inschrijving rond.")
            ->action('Nu betalen', app(PaymentLink::class)->for($rekening));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $namen = collect($this->enrollments)->pluck('first_name')->join(', ', ' en ');

        return [
            'type' => 'inschrijving_ontvangen',
            'title' => "Aanmelding van {$namen} ontvangen",
            'url' => '/dashboard',
        ];
    }
}
