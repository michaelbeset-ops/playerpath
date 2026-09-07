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
     * Het verloop in één woord.
     *
     * Een ouder leest "sterk gegroeid" sneller dan "+7", en een kind weet bij
     * "aandacht" wat het te doen heeft. De grens ligt op twee punten: minder is
     * ruis, want een enkel rapport verschuift het gemiddelde al een beetje.
     *
     * @return array{key: string, label: string}|null
     */
    public static function trend(?int $delta): ?array
    {
        if ($delta === null) {
            return null;
        }

        return match (true) {
            $delta >= 5 => ['key' => 'sterk', 'label' => 'Sterk gegroeid'],
            $delta >= 2 => ['key' => 'groei', 'label' => 'Gegroeid'],
            $delta > -2 => ['key' => 'stabiel', 'label' => 'Stabiel'],
            default => ['key' => 'aandacht', 'label' => 'Aandacht'],
        };
    }

    /**
     * @return array{
     *     points: list<array{date: string, label: string, overall: int, scores: array<string, int>}>,
     *     categories: list<array{category: string, label: string, series: list<int|null>, first: int|null, last: int|null, delta: int|null, trend: array|null}>,
     *     overall: array{series: list<int>, first: int|null, last: int|null, delta: int|null, trend: array|null},
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

            $delta = count($gevuld) >= 2 ? end($gevuld) - $gevuld[0] : null;

            return [
                'category' => $category->value,
                'label' => $category->label(),
                'series' => $series,
                'first' => $gevuld[0] ?? null,
                'last' => $gevuld === [] ? null : end($gevuld),
                'delta' => $delta,
                'trend' => self::trend($delta),
            ];
        }, $player->position->categories());

        $overallSerie = array_map(fn (array $point) => $point['overall'], $points);
        $overallDelta = count($overallSerie) >= 2 ? end($overallSerie) - $overallSerie[0] : null;

        return [
            'points' => $points,
            'categories' => $categories,
            'overall' => [
                'series' => $overallSerie,
                'first' => $overallSerie[0] ?? null,
                'last' => $overallSerie === [] ? null : end($overallSerie),
                'delta' => $overallDelta,
                'trend' => self::trend($overallDelta),
            ],
            'hasEnoughData' => count($points) >= self::MINIMUM_REPORTS,
        ];
    }
}
