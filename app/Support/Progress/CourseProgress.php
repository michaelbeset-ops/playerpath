<?php

namespace App\Support\Progress;

use App\Enums\ReportCategory;
use App\Models\CourseAssessment;
use App\Models\Player;
use App\Models\Product;
use App\Support\Rating\RatingSettings;
use Illuminate\Support\Collection;

/**
 * Voortgang zonder cijfers: begin- en eindniveau per cursus, in kleuren.
 *
 * Hoort bij de inzetkaart. De kaart beloont inzet; hier ziet een kind waar
 * het beter in werd. De trainer legt aan het begin van een cursus of blok per
 * categorie een niveau vast en aan het eind nog een, met een kort verslag.
 *
 * Drie afspraken:
 *
 * - **Alleen voor een cursus of blok** (een aanbod met een begin en een eind:
 *   blok, kamp, small group). Een losse training of privétraining heeft geen
 *   begin en eind om tussen te groeien.
 * - **Nooit vergelijken.** Er is geen overzicht van kinderen naast elkaar voor
 *   ouders; alleen de eigen lijn van het eigen kind.
 * - **De schaal is van de school**: het aantal niveaus en hun namen en kleuren
 *   staan in RatingSettings. Een niveau wordt opgeslagen als positie met het
 *   aantal niveaus van toen, en hier teruggerekend naar de schaal van nu.
 */
class CourseProgress
{
    public static function eligible(Product $product): bool
    {
        return $product->type->hasPeriod();
    }

    /**
     * Een opgeslagen positie op de schaal van nu.
     *
     * @param  list<array{key: string, label: string, color: string}>  $levels
     * @return array{key: string, label: string, color: string, index: int}|null
     */
    public static function level(mixed $index, int $scale, array $levels): ?array
    {
        if ($index === null || $levels === []) {
            return null;
        }

        $aantal = count($levels);
        $positie = (int) $index;

        if ($scale > 1 && $scale !== $aantal) {
            $positie = (int) round($positie * ($aantal - 1) / ($scale - 1));
        }

        $positie = max(0, min($aantal - 1, $positie));

        return [...$levels[$positie], 'index' => $positie];
    }

    /**
     * Per cursus de categorieën met begin en eind, nieuwste cursus eerst.
     *
     * @return list<array<string, mixed>>
     */
    public function forPlayer(Player $player): array
    {
        $levels = RatingSettings::for($player->school)->progressLevels();

        return $player->courseAssessments()
            ->with('product')
            ->get()
            ->filter(fn (CourseAssessment $a) => $a->product !== null)
            ->groupBy('product_id')
            ->map(function (Collection $rijen) use ($player, $levels) {
                $begin = $rijen->firstWhere('moment', CourseAssessment::BEGIN);
                $eind = $rijen->firstWhere('moment', CourseAssessment::EIND);
                $product = $rijen->first()->product;

                return [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'starts_on' => $product->starts_on?->format('d-m-Y'),
                        'ends_on' => $product->ends_on?->format('d-m-Y'),
                    ],
                    'begin_on' => $begin?->assessed_on->format('d-m-Y'),
                    'eind_on' => $eind?->assessed_on->format('d-m-Y'),
                    'begin_note' => $begin?->note,
                    'eind_note' => $eind?->note,
                    'categories' => array_map(fn (ReportCategory $c) => [
                        'category' => $c->value,
                        'label' => $c->label(),
                        'begin' => $begin ? self::level($begin->levels[$c->value] ?? null, $begin->scale, $levels) : null,
                        'eind' => $eind ? self::level($eind->levels[$c->value] ?? null, $eind->scale, $levels) : null,
                    ], $player->position->categories()),
                    'sort' => ($product->starts_on ?? $rijen->min('assessed_on'))->format('Y-m-d'),
                ];
            })
            ->sortByDesc('sort')
            ->values()
            ->all();
    }
}
