<?php

namespace App\Notifications;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Player;
use App\Notifications\Concerns\SendsFromSchool;
use App\Support\Money\Money;
use App\Support\Payments\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Je kind is ingeschreven" — met meteen de manier om te betalen.
 *
 * Dit is het bericht waar een ouder op wacht, en het is ook het moment waarop
 * hij wil afrekenen. Hem daarvoor eerst laten inloggen betekent: wachtwoord
 * kiezen, mail zoeken, opnieuw beginnen. Vandaar de ondertekende betaallink;
 * zie Support\Payments\PaymentLink voor waarom dat kan.
 *
 * Bij contant staat er geen knop maar een zin: dan reken je bij de school af,
 * en een betaalknop zou een gezin twee keer laten betalen.
 */
class InschrijvingGoedgekeurd extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(
        public Player $player,
        public ?Payment $payment = null,
        public bool $waitlist = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Deze mail gaat altijd uit: hij bevestigt een inschrijving en zegt hoe
        // je betaalt. Dat is geen nieuwsbrief om je voor af te melden.
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Op de wachtlijst is niets "rond"; dat hoort het bericht te zeggen,
        // anders wacht een gezin op een training die het nog niet heeft.
        if ($this->waitlist) {
            return $this->schoolMail($notifiable)
                ->subject('Je aanmelding staat op de wachtlijst')
                ->greeting('Hallo')
                ->line("{$this->player->first_name} staat op de wachtlijst. Zodra er een plek vrijkomt, hoor je het van ons.")
                ->line('Je betaalt pas als die plek er is.')
                ->salutation($this->schoolSalutation($notifiable));
        }

        $bericht = $this->schoolMail($notifiable)
            ->subject('De inschrijving van '.$this->player->first_name.' is rond')
            ->greeting('Hallo')
            ->line("{$this->player->first_name} is ingeschreven. Welkom!");

        if ($this->payment === null) {
            return $bericht
                ->line('Je hoort van ons wanneer de trainingen beginnen.')
                ->salutation($this->schoolSalutation($notifiable));
        }

        $bedrag = Money::format($this->payment->amount_cents);

        if ($this->payment->method?->isOffline()) {
            return $bericht
                ->line("Er staat **{$bedrag}** open voor {$this->payment->description}.")
                ->line('Dat reken je af bij de school zelf.')
                ->salutation($this->schoolSalutation($notifiable));
        }

        return $bericht
            ->line("Er staat **{$bedrag}** open voor {$this->payment->description}.")
            ->action('Nu betalen', app(PaymentLink::class)->for($this->payment))
            ->line('De betaallink is '.PaymentLink::DAGEN_GELDIG.' dagen geldig. Lukt het niet, laat het ons dan weten.')
            ->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inschrijving_goedgekeurd',
            'player_id' => $this->player->id,
            'title' => $this->waitlist
                ? "{$this->player->first_name} staat op de wachtlijst"
                : "{$this->player->first_name} is ingeschreven",
            // In de app zelf hoef je geen ondertekende link: daar ben je al
            // ingelogd en staat de betaling gewoon op je eigen scherm.
            'url' => '/billing',
        ];
    }

    /** Alleen ter verduidelijking in tests en logs. */
    public function isOnline(): bool
    {
        return $this->payment?->method === PaymentMethod::Ideal
            || $this->payment?->method === PaymentMethod::DirectDebit;
    }
}
