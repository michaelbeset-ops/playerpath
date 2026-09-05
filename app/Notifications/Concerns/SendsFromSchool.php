<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail die eruitziet alsof hij van de school komt.
 *
 * Een ouder heeft zich ingeschreven bij Keepersschool Rob, niet bij PlayerPath.
 * Staat er "PlayerPath" als afzender in zijn inbox, dan is de kans groot dat
 * hij de mail niet herkent en dus niet opent — en dan is een afgelasting of
 * een betaalherinnering waardeloos.
 *
 * Alleen de afzendernaam verandert; het adres blijft van het platform, want
 * dat is waar de mailserver voor getekend heeft. Een eigen afzenderadres per
 * school vraagt SPF- en DKIM-records bij de school zelf en hoort daarom niet
 * hier maar bij het inrichten van een domein.
 */
trait SendsFromSchool
{
    protected function schoolMail(object $notifiable): MailMessage
    {
        $bericht = new MailMessage;
        $naam = $this->schoolName($notifiable);

        if ($naam !== null) {
            $bericht->from(config('mail.from.address'), $naam);
        }

        return $bericht;
    }

    protected function schoolName(object $notifiable): ?string
    {
        return $notifiable->school?->name;
    }

    protected function schoolSalutation(object $notifiable): string
    {
        return 'Met vriendelijke groet, '.($this->schoolName($notifiable) ?? config('app.name'));
    }
}
