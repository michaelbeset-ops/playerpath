<?php

namespace App\Support\Rating;

use App\Models\School;

/**
 * Beoordelen in kleuren in plaats van cijfers.
 *
 * Een kind van negen een 3 geven voor uitkomen is te hard, ook als het klopt,
 * en een getal nodigt uit tot vergelijken. Scholen die elk kind op zijn eigen
 * niveau laten groeien willen daarom geen cijfers laten zien. Vier kleuren,
 * van werkpunt naar top:
 *
 * | Kleur  | Label    | Vanaf (kaart) | Wat de trainer kiest |
 * |--------|----------|---------------|----------------------|
 * | rood   | Werkpunt | 0             | 4,5                  |
 * | oranje | Op weg   | 55            | 6,2                  |
 * | groen  | Goed     | 70            | 7,7                  |
 * | blauw  | Top      | 85            | 9,2                  |
 *
 * **Onder de motorkap blijft het een getal.** De trainer tikt een kleur aan,
 * er wordt een rapportcijfer opgeslagen dat midden in die kleur valt, en de
 * rest van de app (kaart, demping over drie rapporten, groei, XP, doelen)
 * rekent gewoon door. Wat verandert is alleen wat iemand ziet: nergens een
 * getal, overal de kleur. Zo kan een school later wisselen zonder dat er een
 * rapport verloren gaat.
 *
 * De keuze staat per school in `schools.rating_settings['grading']`, met
 * kleuren als standaard (zie RatingSettings). De grenzen staan hier en
 * nergens anders; de Vue-kant krijgt ze mee als gedeelde prop.
 */
final class Grade
{
    public const KLEUREN = 'kleuren';

    public const CIJFERS = 'cijfers';

    /** @var list<array{key: string, label: string, from: int, score: float}> */
    public const NIVEAUS = [
        ['key' => 'rood', 'label' => 'Werkpunt', 'from' => 0, 'score' => 4.5],
        ['key' => 'oranje', 'label' => 'Op weg', 'from' => 55, 'score' => 6.2],
        ['key' => 'groen', 'label' => 'Goed', 'from' => 70, 'score' => 7.7],
        ['key' => 'blauw', 'label' => 'Top', 'from' => 85, 'score' => 9.2],
    ];

    public static function mode(?School $school): string
    {
        return RatingSettings::for($school)->grading();
    }

    public static function usesColors(?School $school): bool
    {
        return self::mode($school) === self::KLEUREN;
    }

    /**
     * De kleur bij een kaartwaarde (0-100), of null zonder waarde.
     *
     * @return array{key: string, label: string, from: int, score: float}|null
     */
    public static function forRating(int|float|null $rating): ?array
    {
        if ($rating === null) {
            return null;
        }

        $gevonden = self::NIVEAUS[0];

        foreach (self::NIVEAUS as $niveau) {
            if ($rating >= $niveau['from']) {
                $gevonden = $niveau;
            }
        }

        return $gevonden;
    }

    /** Het label bij een kaartwaarde, of null. */
    public static function labelFor(int|float|null $rating): ?string
    {
        return self::forRating($rating)['label'] ?? null;
    }

    /**
     * Voor de gedeelde prop: de modus en de grenzen.
     *
     * @return array{mode: string, levels: list<array{key: string, label: string, from: int, score: float}>}
     */
    public static function share(?School $school): array
    {
        return ['mode' => self::mode($school), 'levels' => self::NIVEAUS];
    }
}
