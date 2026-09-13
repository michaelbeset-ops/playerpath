<?php

namespace App\Support\Rating;

use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Models\XpEvent;
use App\Support\PlayerCard\CalculatePlayerCard;
use Illuminate\Support\Carbon;

/**
 * De rekenkern van de spelerontwikkeling.
 *
 * Drie getallen, en dit is de enige plek waar ze uit elkaar volgen:
 *
 * - **Rating** - hoe goed, relatief aan de leeftijdsgroep. Komt uit de
 *   rapporten via CalculatePlayerCard en wordt naar boven afgerond. Het cijfer
 *   van de trainer wordt nooit achteraf gecorrigeerd op leeftijd: hij beoordeelt
 *   al relatief ("een goede 7 voor een O12"), en een kaart die afwijkt van wat
 *   hij opschreef vertrouwt niemand meer.
 * - **XP** - inzet. Daalt nooit. Komt uit aanwezig zijn en uit rapporten, en
 *   uit groei tussen twee rapporten. Trouw komen brengt je naar goud, ook zonder
 *   talent; dat is het stimuleringsdeel.
 * - **Level** - brons, zilver, goud, elite. Puur op XP, met per school
 *   eventueel een minimale rating als extra eis (standaard uit). Het level zie
 *   je aan het frame van de kaart; de rating aan het getal.
 *
 * De getallen zelf staan in RatingSettings, per school instelbaar.
 */
class RatingEngine
{
    public function __construct(protected CalculatePlayerCard $card) {}

    public function settingsFor(Player $player): RatingSettings
    {
        return RatingSettings::for($player->school);
    }

    // ------------------------------------------------------------------
    // Levels
    // ------------------------------------------------------------------

    /**
     * Het level bij deze XP, en eventueel deze rating.
     *
     * Het hoogste level waarvan de drempel is gehaald. Vereist een level ook
     * een minimale rating en is die er niet, dan telt het niet - je zakt dan
     * naar het level eronder, niet naar brons.
     *
     * @return array{key: string, label: string, xp: int, min_rating: int|null}
     */
    public function level(int $xp, ?int $rating, RatingSettings $settings): array
    {
        $levels = $settings->levels();
        $huidig = $levels[0];

        foreach ($levels as $level) {
            $xpGehaald = $xp >= $level['xp'];
            $ratingGehaald = $level['min_rating'] === null || ($rating !== null && $rating >= $level['min_rating']);

            if ($xpGehaald && $ratingGehaald) {
                $huidig = $level;
            }
        }

        return $huidig;
    }

    /**
     * Het volgende level en hoeveel XP daar nog voor nodig is.
     *
     * Null op het hoogste level: "nog 0 tot elite" is geen doel.
     *
     * @return array{key: string, label: string, xp: int, remaining: int}|null
     */
    public function nextLevel(int $xp, RatingSettings $settings): ?array
    {
        foreach ($settings->levels() as $level) {
            if ($level['xp'] > $xp) {
                return [
                    'key' => $level['key'],
                    'label' => $level['label'],
                    'xp' => $level['xp'],
                    'remaining' => $level['xp'] - $xp,
                ];
            }
        }

        return null;
    }

    /**
     * Alles wat de kaart over level en XP moet weten, in één keer.
     *
     * @return array<string, mixed>
     */
    public function levelState(Player $player): array
    {
        $settings = $this->settingsFor($player);
        // (int): een net aangemaakt model kent zijn databasestandaard (0) nog
        // niet en geeft null terug.
        $xp = (int) $player->xp;
        $level = $this->level($xp, $player->overall_rating, $settings);
        $volgende = $this->nextLevel($xp, $settings);

        // Voortgang binnen het huidige level, voor de balk op de kaart.
        $vloer = $level['xp'];
        $plafond = $volgende['xp'] ?? max($vloer, $xp);
        $bereik = max(1, $plafond - $vloer);

        return [
            'key' => $level['key'],
            'label' => $level['label'],
            'xp' => $xp,
            'next' => $volgende,
            'progress' => $volgende === null ? 100 : (int) round(($xp - $vloer) / $bereik * 100),
        ];
    }

    // ------------------------------------------------------------------
    // XP boeken
    // ------------------------------------------------------------------

    /**
     * XP bijschrijven en de som op de speler bijwerken.
     *
     * Eén boeking per referentie: twee keer dezelfde training afvinken levert
     * geen dubbele punten op.
     */
    public function award(Player $player, string $source, int $points, string $description, ?object $reference = null, ?Carbon $on = null): ?XpEvent
    {
        if ($points <= 0) {
            return null;
        }

        if ($reference !== null && $this->alreadyAwarded($player, $source, $reference)) {
            return null;
        }

        $event = XpEvent::create([
            'player_id' => $player->id,
            'source' => $source,
            'points' => $points,
            'description' => $description,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'occurred_on' => ($on ?? now())->toDateString(),
        ]);

        $this->recalculate($player);

        return $event;
    }

    /**
     * XP intrekken die aan een referentie hing.
     *
     * Voor een aanwezigheid die de trainer terugdraait. XP daalt nooit door
     * prestaties, wel door een correctie van een fout.
     */
    public function revoke(Player $player, string $source, object $reference): void
    {
        XpEvent::query()
            ->where('player_id', $player->id)
            ->where('source', $source)
            ->where('reference_type', $reference::class)
            ->where('reference_id', $reference->getKey())
            ->delete();

        $this->recalculate($player);
    }

    protected function alreadyAwarded(Player $player, string $source, object $reference): bool
    {
        return XpEvent::query()
            ->where('player_id', $player->id)
            ->where('source', $source)
            ->where('reference_type', $reference::class)
            ->where('reference_id', $reference->getKey())
            ->exists();
    }

    /** De som opnieuw uitrekenen en op de speler zetten. */
    public function recalculate(Player $player): int
    {
        $som = (int) XpEvent::query()->where('player_id', $player->id)->sum('points');

        $player->forceFill(['xp' => $som])->save();

        return $som;
    }

    /**
     * Hoeveel XP een rapport oplevert: de basis plus groei.
     *
     * Groei is het verschil in rapportgemiddelde met het vorige rapport, per
     * punt op de kaartschaal, met een plafond. Achteruitgang levert geen
     * minpunten op - XP meet inzet, en een rapport invullen is inzet.
     */
    public function xpForReport(?int $vorigGemiddelde, ?int $nieuwGemiddelde, RatingSettings $settings): array
    {
        $basis = $settings->xpForReport();
        $groei = 0;

        if ($vorigGemiddelde !== null && $nieuwGemiddelde !== null && $nieuwGemiddelde > $vorigGemiddelde) {
            $groei = min($settings->xpGrowthCap(), ($nieuwGemiddelde - $vorigGemiddelde) * $settings->xpPerPointGrowth());
        }

        return ['base' => $basis, 'growth' => $groei, 'total' => $basis + $groei];
    }

    // ------------------------------------------------------------------
    // Leeftijdscategorie en seizoenskaart
    // ------------------------------------------------------------------

    public function categoryFor(Player $player, ?Carbon $op = null): string
    {
        return AgeCategory::forBirthDate($player->date_of_birth, $op, $this->settingsFor($player)->seasonStartMonth());
    }

    /**
     * De categorie vaststellen, en bij een overgang de oude kaart bewaren.
     *
     * Een speler die van O12 naar O14 gaat krijgt een hogere lat. Zijn kaart
     * stort niet in - de demping over de laatste rapporten vangt dat op - maar
     * de oude kaart hoort niet te verdwijnen. Die wordt hier als seizoenskaart
     * weggeschreven, één keer, en daarna wisselt de categorie.
     *
     * Geeft terug of er een overgang was.
     */
    public function syncCategory(Player $player, ?Carbon $op = null): bool
    {
        $op ??= now();
        $settings = $this->settingsFor($player);
        $nieuw = $this->categoryFor($player, $op);
        $oud = $player->age_category;

        if ($oud === $nieuw) {
            return false;
        }

        // De eerste keer is geen overgang: dan is er niets om te bewaren.
        if ($oud !== null) {
            $vorigSeizoen = AgeCategory::seasonLabel($op->copy()->subYear(), $settings->seasonStartMonth());

            PlayerCardSeason::query()->updateOrCreate(
                ['player_id' => $player->id, 'season' => $vorigSeizoen, 'age_category' => $oud],
                [
                    'overall_rating' => $player->overall_rating,
                    'category_ratings' => $player->category_ratings,
                    'xp' => (int) $player->xp,
                    'level' => $this->level((int) $player->xp, $player->overall_rating, $settings)['key'],
                    'report_count' => $player->reports()->count(),
                ],
            );
        }

        $player->forceFill([
            'age_category' => $nieuw,
            'category_changed_on' => $oud === null ? null : $op->toDateString(),
        ])->save();

        return $oud !== null;
    }

    /**
     * Is deze speler kort geleden een categorie omhoog gegaan?
     *
     * Zestig dagen: lang genoeg om er een paar rapporten in de nieuwe categorie
     * bij te hebben, kort genoeg dat de badge iets betekent.
     */
    public function recentlyMovedUp(Player $player): bool
    {
        return $player->category_changed_on !== null
            && $player->category_changed_on->gte(now()->subDays(60));
    }
}
