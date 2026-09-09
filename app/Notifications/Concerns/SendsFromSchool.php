<?php

namespace App\Notifications\Concerns;

use App\Models\School;
use App\Support\Mail\MailBrand;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail die eruitziet alsof hij van de school komt.
 *
 * Een ouder heeft zich ingeschreven bij Keepersschool Rob, niet bij PlayerPath.
 * Staat er "PlayerPath" als afzender in zijn inbox, dan is de kans groot dat
 * hij de mail niet herkent en dus niet opent — en dan is een afgelasting of
 * een betaalherinnering waardeloos.
 *
 * Deze trait zet drie dingen, en die horen bij elkaar:
 *
 * 1. **De afzendernaam** is de school. Het adres blijft van het platform, want
 *    dat is waar de mailserver voor getekend heeft; een eigen afzenderadres per
 *    school vraagt SPF- en DKIM-records bij de school zelf en hoort daarom bij
 *    het inrichten van een domein, niet hier.
 * 2. **Antwoorden gaat naar de school.** Een ouder die op een betaalmail
 *    reageert schrijft aan zijn voetbalschool, niet aan een postbus die niemand
 *    leest. Heeft de school geen contactadres, dan blijft het weg — een
 *    reply-to naar het platform belooft iets wat we niet waarmaken.
 * 3. **De huisstijl** (logo, merkkleur) reist als `merk` mee in de viewData,
 *    zodat de gedeelde mailschil in resources/views/vendor/mail hem kan
 *    gebruiken. Dat is de enige weg: een mail kent geen CSS-variabelen.
 */
trait SendsFromSchool
{
    protected function schoolMail(object $notifiable): MailMessage
    {
        $school = $this->schoolFor($notifiable);

        $bericht = new MailMessage;
        $bericht->viewData['merk'] = MailBrand::describe($school);

        if ($school !== null) {
            $bericht->from(config('mail.from.address'), $school->name);

            if (filled($school->contact_email)) {
                $bericht->replyTo($school->contact_email, $school->name);
            }
        }

        return $bericht;
    }

    protected function schoolFor(object $notifiable): ?School
    {
        return $notifiable->school ?? null;
    }

    protected function schoolName(object $notifiable): ?string
    {
        return $this->schoolFor($notifiable)?->name;
    }

    protected function schoolSalutation(object $notifiable): string
    {
        return 'Met vriendelijke groet, '.($this->schoolName($notifiable) ?? config('app.name'));
    }
}
