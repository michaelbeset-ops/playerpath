<?php

namespace App\Support\Mail;

use App\Http\Controllers\Schools\EnrollmentSettingsController;
use App\Models\School;
use App\Support\Branding\BrandColor;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * De huisstijl van een school, klaar voor een inbox.
 *
 * Een mailprogramma kent geen CSS-variabelen en geen thema's, dus de merkkleur
 * moet als inline kleur mee de mail in. Deze klasse is de enige plek die dat
 * uitrekent, zodat de kop, de knop en de voet van elke mail dezelfde kleur
 * gebruiken en er nooit ergens een hardgecodeerd groen blijft staan.
 *
 * Drie dingen die je niet moet omdraaien:
 *
 * 1. **Zonder school is het PlayerPath.** Een mail aan een platformbeheerder
 *    of een uitnodiging zonder school hoort niet leeg te zijn.
 * 2. **De kleur wordt zo nodig bijgesteld** (BrandColor::adjustedForContrast),
 *    precies zoals in de app. Een school die knalgeel koos krijgt anders een
 *    knop met onleesbare letters, en in een mail kun je dat niet herstellen
 *    met een andere themakeuze.
 * 3. **Het logo wordt een absoluut adres.** Een mailprogramma heeft niets aan
 *    /storage/logos/x.png.
 */
final class MailBrand
{
    /** Het groen van PlayerPath op de lichte kant; diep genoeg voor witte tekst. */
    public const STANDAARD_KLEUR = '#12813D';

    /**
     * Wie een mail van PlayerPath zelf ondertekent. Een eigenaar is klant
     * van ons, niet van zijn eigen school: die mails komen van ons.
     */
    public const PLATFORM_AFZENDER = 'Michael van PlayerPath';

    /**
     * De school op een mail zetten: afzendernaam, antwoordadres en huisstijl.
     *
     * Dit is de enige plek waar dat gebeurt. Notificaties komen er via
     * SendsFromSchool langs; de mails van Laravel zelf (wachtwoord vergeten,
     * e-mailadres bevestigen) zijn geen notificatieklasse van ons en worden in
     * FortifyServiceProvider met de hand hierlangs gestuurd. Zou dat niet
     * gebeuren, dan draagt precies de eerste mail die een schooleigenaar
     * krijgt de naam PlayerPath.
     */
    public static function apply(MailMessage $bericht, ?School $school): MailMessage
    {
        $bericht->viewData['merk'] = self::describe($school);

        if ($school === null) {
            return $bericht;
        }

        // Alleen de afzendernaam; het adres blijft van het platform, want daar
        // staan de SPF- en DKIM-records op.
        $bericht->from(config('mail.from.address'), $school->name);

        if (filled($school->contact_email)) {
            $bericht->replyTo($school->contact_email, $school->name);
        }

        return $bericht;
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(?School $school): array
    {
        $kleur = $school?->brand_color !== null && BrandColor::isValid($school->brand_color)
            ? BrandColor::fromHex($school->brand_color)->adjustedForContrast()
            : null;

        return [
            'name' => $school?->name ?? config('app.name'),
            // Zonder school komt de mail van PlayerPath zelf (een
            // platformbeheerder heeft geen school), en dan hoort ons logo erboven.
            // Bij een school nooit: zonder eigen logo krijgt ze haar naam, want
            // een ouder heeft zich bij haar aangemeld en niet bij ons.
            'logo' => match (true) {
                $school === null => url('brand/logo.png'),
                $school->logo_path !== null => url(Storage::url($school->logo_path)),
                default => null,
            },
            // Vaste maat voor ons eigen logo (1060 × 320): Outlook negeert
            // CSS-breedtes op plaatjes en toont hem anders op ware grootte. Een
            // schoollogo heeft onbekende verhoudingen en houdt de CSS-grenzen.
            'logoWidth' => $school === null ? 180 : null,
            'logoHeight' => $school === null ? 54 : null,
            'color' => $kleur?->toHex() ?? self::STANDAARD_KLEUR,
            'onColor' => $kleur === null ? '#FFFFFF' : self::leesbaar($kleur),
            'email' => $school?->contact_email,
            // Zonder school: ons eigen nummer, zodat een nieuwe eigenaar weet
            // wie hij belt als het niet lukt.
            'phone' => $school === null ? EnrollmentSettingsController::SUPPORT_PHONE : $school->contact_phone,
            'url' => config('app.url'),
            // Komt de mail van een school? Dan staat onderaan "Verstuurd met
            // PlayerPath" (zij is de afzender, wij het gereedschap). Zonder
            // school is PlayerPath zelf de afzender, met ons telefoonnummer.
            'fromSchool' => $school !== null,
        ];
    }

    /**
     * Wat er uit de viewData van een mail komt, of de terugval.
     *
     * Elke mail die via SendsFromSchool loopt zet dit; een mail die dat niet
     * doet krijgt hier gewoon PlayerPath, in plaats van een lege kop.
     *
     * @param  array<string, mixed>|null  $merk
     * @return array<string, mixed>
     */
    public static function resolve(?array $merk): array
    {
        return $merk ?? self::describe(null);
    }

    /** Wit of bijna-zwart op deze kleur, als hex - een mail kent geen HSL-tokens. */
    private static function leesbaar(BrandColor $kleur): string
    {
        return $kleur->readableForeground() === '0 0% 100%' ? '#FFFFFF' : '#0F172A';
    }
}
