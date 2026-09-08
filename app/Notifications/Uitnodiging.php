<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * "Je bent uitgenodigd" — de eerste mail die iemand van deze school krijgt.
 *
 * Hij komt uit naam van de school en niet van PlayerPath: een ouder heeft zijn
 * kind bij Keepersschool Rob aangemeld en kent ons niet. Staat er "PlayerPath"
 * boven, dan wordt de mail niet herkend en dus niet geopend — en dan is de hele
 * uitnodiging weg.
 *
 * Drie dingen die deze mail moet doen, en verder niets:
 *
 * 1. **Zeggen van wie hij komt** — logo en naam van de school bovenaan.
 * 2. **In twee zinnen zeggen wat je eraan hebt.** Niet wat het product allemaal
 *    kan; wat déze ontvanger krijgt. Voor een ouder is dat de kaart van zijn
 *    kind, voor een trainer zijn rooster.
 * 3. **Eén knop.** Twee knoppen betekent kiezen, en dan klikt een deel op geen
 *    van beide.
 *
 * Hij gaat naar een e-mailadres en niet naar een gebruiker — het account
 * bestaat immers nog niet — dus de school komt uit de uitnodiging en niet uit
 * `$notifiable`. Daarom gebruikt deze klasse `SendsFromSchool` níét.
 */
class Uitnodiging extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Alleen mail: de ontvanger heeft nog geen account om iets in te tonen.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->invitation->school;
        $isOuder = $this->invitation->role === Role::Ouder->value;

        $kinderen = $this->invitation->players()->pluck('first_name')->all();

        return (new MailMessage)
            ->from(config('mail.from.address'), $school->name)
            ->subject($school->name.' nodigt je uit')
            ->markdown('mail.uitnodiging', [
                'school' => $school,
                'logo' => $this->logo($school),
                'naam' => $this->invitation->name,
                'isOuder' => $isOuder,
                'kinderen' => $kinderen,
                'url' => url('/uitnodiging/'.$this->invitation->token),
                'verlooptOp' => $this->invitation->expires_at->translatedFormat('j F Y'),
            ]);
    }

    /**
     * Het logo van de school, als er een is.
     *
     * Een absolute URL, want een mailprogramma heeft niets aan een pad. Zonder
     * logo staat de naam er groot; het logo van PlayerPath hoort hier niet, dat
     * zou de indruk wekken dat de mail van ons komt.
     */
    protected function logo(?object $school): ?string
    {
        return $school?->logo_path === null ? null : url(Storage::url($school->logo_path));
    }
}
