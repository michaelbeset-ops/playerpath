<?php

namespace App\Support\PlayerCard;

use App\Enums\ReportCategory;
use App\Models\Player;

/**
 * Rekent rapporten door naar de spelerskaart.
 *
 * De regels, bewust simpel en uitlegbaar aan een trainer:
 *
 * 1. Per categorie tellen de laatste REPORTS_IN_AVERAGE rapporten mee waarin
 *    die categorie een cijfer kreeg. Eén mindere training verpest de kaart dus
 *    niet, maar echte groei is binnen een paar rapporten zichtbaar.
 * 2. Het gemiddelde cijfer (1-10) wordt maal tien: een 8 leest als 80.
 * 3. De overall rating is het gemiddelde van de sub-scores, afgerond.
 * 4. Zonder rapporten heeft een speler geen kaartcijfers (null, niet 0).
 */
class CalculatePlayerCard
{
    /** Hoeveel recente rapporten meewegen per categorie. */
    public const REPORTS_IN_AVERAGE = 3;

    public function for(Player $player): PlayerCard
    {
        $reports = $player->reports()
            ->newestFirst()
            ->with('scores')
            ->limit(self::REPORTS_IN_AVERAGE)
            ->get();

        $categories = $player->position->categories();

        /** @var array<string, list<int>> $verzameld */
        $verzameld = [];

        foreach ($reports as $report) {
            foreach ($report->scores as $score) {
                $verzameld[$score->category->value][] = $score->score;
            }
        }

        $subScores = [];

        foreach ($categories as $category) {
            $cijfers = $verzameld[$category->value] ?? [];

            if ($cijfers === []) {
                continue;
            }

            $subScores[$category->value] = (int) round(array_sum($cijfers) / count($cijfers) * 10);
        }

        $overall = $subScores === []
            ? null
            : (int) round(array_sum($subScores) / count($subScores));

        return new PlayerCard(
            overall: $overall,
            categoryRatings: $subScores,
            reportCount: $player->reports()->count(),
        );
    }

    /** Berekent de kaart opnieuw en slaat het resultaat op bij de speler. */
    public function refresh(Player $player): PlayerCard
    {
        $card = $this->for($player);

        $player->forceFill([
            'overall_rating' => $card->overall,
            'category_ratings' => $card->categoryRatings === [] ? null : $card->categoryRatings,
            'rated_at' => $card->overall === null ? null : now(),
        ])->save();

        return $card;
    }

    /**
     * De categorieën van een speler met hun huidige cijfer, klaar voor het scherm.
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
            'rating' => $ratings[$category->value] ?? null,
        ], $player->position->categories());
    }
}
