<?php

namespace App\Support\PlayerCard;

use App\Enums\AttendanceStatus;
use App\Enums\GoalStatus;
use App\Models\Player;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\RatingSettings;

/**
 * Mijlpalen en niveau voor de spelerskaart.
 *
 * Alles wordt **afgeleid** uit rapporten en aanwezigheid; er is geen tabel met
 * badges. Daardoor kan een badge niet los komen te staan van de werkelijkheid,
 * en kost een nieuwe badge alleen een regel hier.
 *
 * De drempels zijn bewust haalbaar: een kaart die nooit iets oplevert voelt
 * dood, en dat is precies wat dit product niet moet zijn.
 */
class PlayerBadges
{
    /** @return array{key: string, label: string, description: string} */
    /**
     * Het level van een speler.
     *
     * Sinds de rekenkern op XP, niet meer op rating: het level beloont inzet,
     * de rating zegt hoe goed. Zie Support\Rating\RatingEngine. Deze methode
     * blijft bestaan als ingang voor de kaart, zodat het niveau overal uit
     * dezelfde bron komt.
     *
     * @return array{key: string, label: string, description: string, xp: int, next: array|null, progress: int}
     */
    public function level(Player $player): array
    {
        $state = app(RatingEngine::class)->levelState($player);

        return [
            'key' => $state['key'],
            'label' => $state['label'],
            'description' => $state['next'] === null
                ? 'Het hoogste level'
                : "Nog {$state['next']['remaining']} XP tot {$state['next']['label']}",
            'xp' => $state['xp'],
            'next' => $state['next'],
            'progress' => $state['progress'],
        ];
    }

    /**
     * Alle mijlpalen die er bestaan, in vaste volgorde.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function catalogue(): array
    {
        return [
            ['key' => 'eerste_rapport', 'label' => 'Op de kaart', 'description' => 'Je eerste rapport is binnen'],
            ['key' => 'vijf_rapporten', 'label' => 'Vaste waarde', 'description' => 'Vijf rapporten of meer'],
            ['key' => 'groei', 'label' => 'In de lift', 'description' => 'Vijf punten gegroeid sinds je eerste rapport'],
            ['key' => 'sterke_groei', 'label' => 'Grote sprong', 'description' => 'Tien punten gegroeid sinds je eerste rapport'],
            ['key' => 'uitblinker', 'label' => 'Uitblinker', 'description' => 'Een categorie op 85 of hoger'],
            ['key' => 'compleet', 'label' => 'Compleet', 'description' => 'Alle categorieën op 70 of hoger'],
            ['key' => 'doel_gehaald', 'label' => 'Doelgericht', 'description' => 'Een ontwikkelingsdoel gehaald'],
            ['key' => 'aanwezig_vijf', 'label' => 'Altijd op tijd', 'description' => 'Vijf trainingen aanwezig'],
            ['key' => 'aanwezig_tien', 'label' => 'Onmisbaar', 'description' => 'Tien trainingen aanwezig'],
            // De inzetkaart: mijlpalen voor wie er is en er voor gaat.
            ['key' => 'eerste_inzet', 'label' => 'Van start', 'description' => 'Je eerste inzetpunten verdiend'],
            ['key' => 'doorzetter', 'label' => 'Doorzetter', 'description' => 'Vijf keer hard gewerkt of beter'],
            ['key' => 'luisteraar', 'label' => 'Goede luisteraar', 'description' => 'Vijf keer een top houding of beter'],
            ['key' => 'topinzet', 'label' => 'Uitblinker', 'description' => 'Drie keer de hoogste inzet'],
            ['key' => 'zilveren_kaart', 'label' => 'Zilveren kaart', 'description' => 'Je kaart werd zilver'],
            ['key' => 'gouden_kaart', 'label' => 'Gouden kaart', 'description' => 'Je kaart werd goud'],
        ];
    }

    /** Mijlpalen die over rapporten en cijfers gaan: niet bij de inzetkaart. */
    public const ALLEEN_PRESTATIE = ['eerste_rapport', 'vijf_rapporten', 'groei', 'sterke_groei', 'uitblinker', 'compleet', 'doel_gehaald'];

    /** Mijlpalen die over inzetpunten gaan: niet bij de prestatiekaart. */
    public const ALLEEN_INZET = ['eerste_inzet', 'doorzetter', 'luisteraar', 'topinzet', 'zilveren_kaart', 'gouden_kaart'];

    /**
     * De mijlpalen die bij deze kaart horen.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function catalogueFor(string $mode): array
    {
        $weg = $mode === RatingSettings::INZET ? self::ALLEEN_PRESTATIE : self::ALLEEN_INZET;

        return array_values(array_filter(self::catalogue(), fn (array $badge) => ! in_array($badge['key'], $weg, true)));
    }

    /**
     * De mijlpalen die voor deze speler gelden (BadgeSettings), met of ze
     * behaald zijn.
     *
     * @return list<array{key: string, label: string, description: string, earned: bool}>
     */
    public function for(Player $player, PlayerProgress $progress): array
    {
        $settings = RatingSettings::for($player->school);
        $voortgang = $progress->for($player);
        $ratings = $player->category_ratings ?? [];

        $aantalRapporten = count($voortgang['points']);
        $groei = $voortgang['overall']['delta'];

        $aanwezig = $player->attendances()
            ->where('status', AttendanceStatus::Present->value)
            ->count();

        $behaald = [
            'eerste_rapport' => $aantalRapporten >= 1,
            'vijf_rapporten' => $aantalRapporten >= 5,
            'groei' => $groei !== null && $groei >= 5,
            'sterke_groei' => $groei !== null && $groei >= 10,
            'uitblinker' => $ratings !== [] && max($ratings) >= 85,
            'compleet' => $ratings !== [] && min($ratings) >= 70,
            'doel_gehaald' => $player->goals()->where('status', GoalStatus::Achieved->value)->exists(),
            'aanwezig_vijf' => $aanwezig >= 5,
            'aanwezig_tien' => $aanwezig >= 10,
            ...$this->inzetBehaald($player, $settings),
        ];

        $instellingen = BadgeSettings::for($player->school);
        $catalogus = self::catalogueFor($settings->cardMode());

        // Alleen wat bij deze kaart hoort. Blijft er niets over (een school die
        // wisselde en haar mijlpalen nog niet aanpaste), dan de standaard.
        $geldig = array_values(array_intersect($instellingen->keysFor($player->age_category), array_column($catalogus, 'key')))
            ?: BadgeSettings::standardFor($settings->cardMode());

        $lijst = array_values(array_map(
            fn (array $badge) => [...$badge, 'earned' => $behaald[$badge['key']] ?? false],
            array_filter($catalogus, fn (array $badge) => in_array($badge['key'], $geldig, true)),
        ));

        // De eigen mijlpalen van de school erachter. Die worden niet afgeleid
        // maar toegekend; "behaald" is dus: er ligt een toekenning.
        $eigen = $instellingen->customBadges();

        if ($eigen !== []) {
            $toegekend = $player->awardedBadges()->pluck('badge_key')->all();

            foreach ($eigen as $badge) {
                $lijst[] = [...$badge, 'earned' => in_array($badge['key'], $toegekend, true)];
            }
        }

        return $lijst;
    }

    /**
     * De mijlpalen van de inzetkaart. "Of beter" is elke trede boven de
     * eerste; de hoogste trede is de laatste die de school heeft ingesteld.
     *
     * @return array<string, bool>
     */
    protected function inzetBehaald(Player $player, RatingSettings $settings): array
    {
        $inzet = $player->effortRatings()->get(['effort', 'attitude']);
        $hoogste = collect($settings->effortLevels())->last()['key'] ?? null;

        $levels = array_column($settings->levels(), 'key');
        $huidig = array_search(app(RatingEngine::class)->levelState($player)['key'], $levels, true);

        return [
            'eerste_inzet' => $inzet->contains(fn ($r) => $r->effort !== null || $r->attitude !== null),
            'doorzetter' => $inzet->filter(fn ($r) => $r->effort !== null && $r->effort !== 'n1')->count() >= 5,
            'luisteraar' => $inzet->filter(fn ($r) => $r->attitude !== null && $r->attitude !== 'n1')->count() >= 5,
            'topinzet' => $hoogste !== null && $inzet->where('effort', $hoogste)->count() >= 3,
            'zilveren_kaart' => $huidig !== false && $huidig >= 1,
            'gouden_kaart' => $huidig !== false && $huidig >= 2,
        ];
    }
}
