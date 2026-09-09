<?php

namespace App\Support\Mail;

use App\Models\School;
use App\Support\Branding\BrandColor;
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
     * @return array<string, mixed>
     */
    public static function describe(?School $school): array
    {
        $kleur = $school?->brand_color !== null && BrandColor::isValid($school->brand_color)
            ? BrandColor::fromHex($school->brand_color)->adjustedForContrast()
            : null;

        return [
            'name' => $school?->name ?? config('app.name'),
            'logo' => $school?->logo_path !== null ? url(Storage::url($school->logo_path)) : null,
            'color' => $kleur?->toHex() ?? self::STANDAARD_KLEUR,
            'onColor' => $kleur === null ? '#FFFFFF' : self::leesbaar($kleur),
            'email' => $school?->contact_email,
            'phone' => $school?->contact_phone,
            'url' => config('app.url'),
            // Wel of niet "Verstuurd met PlayerPath" onderaan: bij een school
            // wel (zij is de afzender, wij het gereedschap), bij PlayerPath
            // zelf niet — dan staat er twee keer hetzelfde.
            'platform' => $school !== null,
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

    /** Wit of bijna-zwart op deze kleur, als hex — een mail kent geen HSL-tokens. */
    private static function leesbaar(BrandColor $kleur): string
    {
        return $kleur->readableForeground() === '0 0% 100%' ? '#FFFFFF' : '#0F172A';
    }
}
