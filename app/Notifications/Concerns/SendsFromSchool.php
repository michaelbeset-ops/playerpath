<?php

namespace App\Notifications\Concerns;

use App\Models\School;
use App\Support\Mail\MailBrand;
use App\Support\Tenancy\WithSchool;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail die eruitziet alsof hij van de school komt.
 *
 * Een ouder heeft zich ingeschreven bij Keepersschool Rob, niet bij PlayerPath.
 * Staat er "PlayerPath" als afzender in zijn inbox, dan is de kans groot dat
 * hij de mail niet herkent en dus niet opent - en dan is een afgelasting of
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
 *    leest. Heeft de school geen contactadres, dan blijft het weg - een
 *    reply-to naar het platform belooft iets wat we niet waarmaken.
 * 3. **De huisstijl** (logo, merkkleur) reist als `merk` mee in de viewData,
 *    zodat de gedeelde mailschil in resources/views/vendor/mail hem kan
 *    gebruiken. Dat is de enige weg: een mail kent geen CSS-variabelen.
 */
trait SendsFromSchool
{
    /**
     * De school van de ontvanger geldt zolang deze melding verwerkt wordt.
     *
     * Zonder dit staat de global scope dicht in de queue-worker en levert elke
     * query binnen `toMail()` of `toArray()` niets op - zonder foutmelding.
     * Zie `Support\Tenancy\WithSchool`.
     *
     * @return list<object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [new WithSchool($this->schoolFor($notifiable)?->id)];
    }

    protected function schoolMail(object $notifiable): MailMessage
    {
        return MailBrand::apply(new MailMessage, $this->schoolFor($notifiable));
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
