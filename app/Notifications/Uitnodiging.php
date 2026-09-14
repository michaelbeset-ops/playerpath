<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Http\Controllers\Schools\EnrollmentSettingsController;
use App\Models\Invitation;
use App\Support\Mail\MailBrand;
use App\Support\Tenancy\WithSchool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Welkom" - de eerste mail die iemand van deze school krijgt.
 *
 * Voor een ouder of trainer komt hij uit naam van de school en niet van
 * PlayerPath: een ouder heeft zijn kind bij Keepersschool Rob aangemeld en kent
 * ons niet. Staat er "PlayerPath" boven, dan wordt de mail niet herkend en dus
 * niet geopend.
 *
 * **Voor een eigenaar is het andersom.** Die is klant van PlayerPath en krijgt
 * zijn account van ons, via platformbeheer. Zijn school bestaat net; als
 * afzender kent hij hem nog niet, en "vraag Keepersschool Rob om een nieuwe
 * uitnodiging" is voor de eigenaar van Keepersschool Rob een doodlopende weg.
 * Die mail draagt dus ons logo, komt van PlayerPath, is ondertekend door
 * `MailBrand::PLATFORM_AFZENDER` en noemt ons telefoonnummer.
 *
 * Drie dingen die deze mail moet doen, en verder niets:
 *
 * 1. **Zeggen van wie hij komt.**
 * 2. **In twee zinnen zeggen wat je eraan hebt.** Niet wat het product allemaal
 *    kan; wat déze ontvanger krijgt.
 * 3. **Eén knop.**
 *
 * Hij gaat naar een e-mailadres en niet naar een gebruiker - het account
 * bestaat immers nog niet - dus de school komt uit de uitnodiging en niet uit
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

    /**
     * De school komt hier uit de uitnodiging en niet uit de ontvanger - die
     * heeft nog geen account. Zonder dit staat de scope dicht in de worker en
     * levert `players()` niets op: dan noemt de mail "je kind" in plaats van de
     * naam van het kind, precies in het bericht dat vertrouwen moet wekken.
     *
     * @return list<object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [new WithSchool($this->invitation->school_id)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->invitation->school;
        $isOuder = $this->invitation->role === Role::Ouder->value;
        $vanPlatform = $this->invitation->role === Role::Eigenaar->value;

        $kinderen = $this->invitation->players()->pluck('first_name')->all();

        $bericht = (new MailMessage)
            ->from(config('mail.from.address'), $vanPlatform ? config('app.name') : $school->name)
            ->subject($vanPlatform
                ? 'Welkom bij PlayerPath: je account voor '.$school->name
                : 'Welkom bij '.$school->name);

        // Antwoorden hoort bij de school te komen: iemand die op een
        // uitnodiging reageert schrijft aan zijn voetbalschool. Een eigenaar
        // schrijft aan ons, en dat is het standaardadres.
        if (! $vanPlatform && filled($school->contact_email)) {
            $bericht->replyTo($school->contact_email, $school->name);
        }

        return $bericht->markdown('mail.uitnodiging', [
            'merk' => MailBrand::describe($vanPlatform ? null : $school),
            'school' => $school,
            'naam' => $this->invitation->name,
            'isOuder' => $isOuder,
            'rol' => $this->invitation->role,
            'kinderen' => $kinderen,
            'url' => url('/uitnodiging/'.$this->invitation->token),
            'verlooptOp' => $this->invitation->expires_at->translatedFormat('j F Y'),
            'vanPlatform' => $vanPlatform,
            'afzender' => $vanPlatform ? MailBrand::PLATFORM_AFZENDER : $school->name,
            'telefoon' => EnrollmentSettingsController::SUPPORT_PHONE,
        ]);
    }
}
