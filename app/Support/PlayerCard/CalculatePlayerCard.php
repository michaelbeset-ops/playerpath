<?php

namespace App\Support\PlayerCard;

use App\Enums\ReportCategory;
use App\Models\Player;
use App\Models\Report;
use App\Support\Rating\RatingSettings;

/**
 * Rekent rapporten door naar de spelerskaart.
 *
 * De regels, bewust simpel en uitlegbaar aan een trainer:
 *
 * 1. Per categorie tellen de laatste REPORTS_IN_AVERAGE rapporten mee waarin
 *    die categorie een cijfer kreeg. Eén mindere training verpest de kaart dus
 *    niet, maar echte groei is binnen een paar rapporten zichtbaar.
 * 2. Het gemiddelde cijfer (1-10) wordt maal tien: een 8 leest als 80.
 * 3. De overall rating is het gemiddelde van de sub-scores.
 * 4. Zonder rapporten heeft een speler geen kaartcijfers (null, niet 0).
 *
 * ## Precies bewaren, naar boven tonen
 *
 * Een trainer geeft cijfers met een decimaal (7,4). Intern blijft dat precies
 * staan, want daar hangt de voortgangsberekening aan: zou je bij elke stap
 * afronden, dan telt een halve punt groei op papier als een hele en klopt de
 * lijn in de grafiek niet meer met wat er is opgeschreven.
 *
 * **Naar buiten wordt altijd naar boven afgerond.** Een 74,5 op de kaart wordt
 * 75, nooit 74. Dat is een keuze in het voordeel van het kind: een cijfer op
 * zijn kaart hoort nooit lager te zijn dan wat hij verdiende. Die afronding
 * staat op één plek — `afronden()` hieronder — zodat de kaart, het rapport en
 * elk scherm hetzelfde getal laten zien.
 */
class CalculatePlayerCard
{
    /** Hoeveel recente rapporten meewegen per categorie. */
    public const REPORTS_IN_AVERAGE = 3;

    /**
     * De enige plek waar een kaartcijfer wordt afgerond.
     *
     * Altijd naar boven. Roep dit aan in plaats van round() of (int): zodra
     * er twee manieren van afronden in de app zitten, laat het ene scherm 74
     * zien waar het andere 75 zegt, en dan gelooft niemand het meer.
     */
    public static function afronden(int|float|null $waarde): ?int
    {
        return $waarde === null ? null : (int) ceil(round($waarde, 4));
    }

    public function for(Player $player): PlayerCard
    {
        $reports = $player->reports()
            ->newestFirst()
            ->with('scores')
            // Per school instelbaar; de constante is de standaard.
            ->limit(RatingSettings::for($player->school)->reportsInAverage())
            ->get();

        $subScores = $this->subScores($reports, $player);

        $overall = $subScores === []
            ? null
            : round(array_sum($subScores) / count($subScores), 2);

        return new PlayerCard(
            overall: $overall,
            categoryRatings: $subScores,
            reportCount: $player->reports()->count(),
        );
    }

    /**
     * Wat het laatste rapport aan de kaart veranderde, per categorie.
     *
     * De kaart van nu (de laatste N rapporten) tegenover de kaart zoals hij
     * stond vóór het laatste rapport (de N rapporten daarvoor). Zo staat er op
     * de kaart een pijltje per categorie dat precies zegt wat de trainer de
     * vorige keer zag veranderen — dezelfde demping als de kaart zelf, dus een
     * uitschieter geeft geen pijl van twintig punten.
     *
     * @return array<string, int|null> categorie => verschil, null zonder vorige stand
     */
    public function deltas(Player $player): array
    {
        $n = RatingSettings::for($player->school)->reportsInAverage();

        $reports = $player->reports()->newestFirst()->with('scores')->limit($n + 1)->get();

        if ($reports->count() < 2) {
            return [];
        }

        $nu = $this->subScores($reports->take($n), $player);
        $vorige = $this->subScores($reports->slice(1, $n), $player);

        $deltas = [];

        foreach ($player->position->categories() as $category) {
            $key = $category->value;
            $deltas[$key] = isset($nu[$key], $vorige[$key])
                ? self::afronden($nu[$key]) - self::afronden($vorige[$key])
                : null;
        }

        return $deltas;
    }

    /**
     * Per categorie het gemiddelde cijfer maal tien, precies bewaard.
     *
     * @param  iterable<int, Report>  $reports
     * @return array<string, float>
     */
    protected function subScores(iterable $reports, Player $player): array
    {
        /** @var array<string, list<float>> $verzameld */
        $verzameld = [];

        foreach ($reports as $report) {
            foreach ($report->scores as $score) {
                $verzameld[$score->category->value][] = (float) $score->score;
            }
        }

        $subScores = [];

        foreach ($player->position->categories() as $category) {
            $cijfers = $verzameld[$category->value] ?? [];

            if ($cijfers === []) {
                continue;
            }

            // Precies bewaren: hier hangt de voortgangsberekening aan.
            $subScores[$category->value] = round(array_sum($cijfers) / count($cijfers) * 10, 2);
        }

        return $subScores;
    }

    /**
     * Berekent de kaart opnieuw en slaat het resultaat op bij de speler.
     *
     * `overall_rating` is het getal dat overal getoond wordt, dus dat gaat er
     * afgerond in. `category_ratings` blijft precies staan: dat is de bron voor
     * de voortgang, en die hoort niet stapsgewijs op te lopen door afronding.
     */
    public function refresh(Player $player): PlayerCard
    {
        $card = $this->for($player);

        $player->forceFill([
            'overall_rating' => self::afronden($card->overall),
            'category_ratings' => $card->categoryRatings === [] ? null : $card->categoryRatings,
            'rated_at' => $card->overall === null ? null : now(),
        ])->save();

        return $card;
    }

    /**
     * De categorieën van een speler met hun huidige cijfer, klaar voor het scherm.
     *
     * Afgerond naar boven: dit gaat naar de kaart en naar het rapportscherm.
     *
     * @return list<array{category: string, label: string, hint: string, rating: int|null}>
     */
    public function breakdown(Player $player): array
    {
        $ratings = $player->category_ratings ?? [];

        return array_map(fn (ReportCategory $category) => [
            'category' => $category->value,
            'label' => $category->label(),
            'hint' => $category->hint(),
            'rating' => self::afronden($ratings[$category->value] ?? null),
        ], $player->position->categories());
    }
}
