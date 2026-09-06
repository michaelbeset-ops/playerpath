<?php

namespace App\Support\PlayerCard;

use App\Models\Player;
use App\Models\Report;

/**
 * De groei van een speler over de tijd, klaar om te tekenen.
 *
 * Elk rapport is één punt op de lijn. We tonen het **cijfer van dat rapport**
 * maal tien, niet het doorgerekende kaartgemiddelde: anders zie je een
 * afgevlakte lijn en niet wat de trainer die dag opschreef.
 */
class PlayerProgress
{
    /** Minimaal aantal rapporten voordat een grafiek iets zegt. */
    public const MINIMUM_REPORTS = 2;

    /**
     * @return array{
     *     points: list<array{date: string, label: string, overall: int, scores: array<string, int>}>,
     *     categories: list<array{category: string, label: string, series: list<int|null>, first: int|null, last: int|null, delta: int|null}>,
     *     overall: array{series: list<int>, first: int|null, last: int|null, delta: int|null},
     *     hasEnoughData: bool
     * }
     */
    public function for(Player $player): array
    {
        $reports = $player->reports()
            ->with('scores')
            ->orderBy('reported_on')
            ->orderBy('id')
            ->get();

        $points = $reports->map(function (Report $report) {
            $scores = $report->scoresByCategory();

            return [
                'date' => $report->reported_on->format('Y-m-d'),
                'label' => $report->reported_on->format('d-m-Y'),
                // Naar boven afgerond, net als op de kaart: anders staat er
                // in de grafiek een ander getal dan op het profiel.
                'overall' => $scores === [] ? 0 : (CalculatePlayerCard::afronden(array_sum($scores) / count($scores) * 10) ?? 0),
                'scores' => array_map(fn (float $score) => CalculatePlayerCard::afronden($score * 10), $scores),
            ];
        })->values()->all();

        $categories = array_map(function ($category) use ($points) {
            $series = array_map(
                fn (array $point) => $point['scores'][$category->value] ?? null,
                $points
            );

            $gevuld = array_values(array_filter($series, fn ($waarde) => $waarde !== null));

            return [
                'category' => $category->value,
                'label' => $category->label(),
                'series' => $series,
                'first' => $gevuld[0] ?? null,
                'last' => $gevuld === [] ? null : end($gevuld),
                'delta' => count($gevuld) >= 2 ? end($gevuld) - $gevuld[0] : null,
            ];
        }, $player->position->categories());

        $overallSerie = array_map(fn (array $point) => $point['overall'], $points);

        return [
            'points' => $points,
            'categories' => $categories,
            'overall' => [
                'series' => $overallSerie,
                'first' => $overallSerie[0] ?? null,
                'last' => $overallSerie === [] ? null : end($overallSerie),
                'delta' => count($overallSerie) >= 2 ? end($overallSerie) - $overallSerie[0] : null,
            ],
            'hasEnoughData' => count($points) >= self::MINIMUM_REPORTS,
        ];
    }
}
