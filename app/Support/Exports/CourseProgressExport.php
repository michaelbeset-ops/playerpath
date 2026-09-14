<?php

namespace App\Support\Exports;

use App\Enums\ReportCategory;
use App\Models\CourseAssessment;
use App\Support\Progress\CourseProgress;
use App\Support\Rating\RatingSettings;
use App\Support\Tenancy\Tenancy;

/**
 * Eén regel per kind per cursus: begin- en eindniveau per onderdeel en het
 * verslag. De niveaus als woorden ("Op weg → Goed"), nooit als getal.
 */
class CourseProgressExport implements Export, FormattedExport
{
    public function key(): string
    {
        return 'cursusvoortgang';
    }

    public function title(): string
    {
        return 'Voortgang per cursus';
    }

    public function description(): string
    {
        return 'Per cursus of blok en per kind het begin- en eindniveau per onderdeel, met het verslag van de trainer.';
    }

    public function supportsDateRange(): bool
    {
        return true;
    }

    public function headings(): array
    {
        return ['Cursus', 'Speler', 'Begin vastgelegd', 'Eind vastgelegd', 'Niveaus (begin → eind)', 'Verslag'];
    }

    public function types(): array
    {
        return ['text', 'text', 'date', 'date', 'text', 'text'];
    }

    public function totals(): array
    {
        return [];
    }

    public function rows(array $filters): iterable
    {
        $levels = RatingSettings::for(app(Tenancy::class)->school())->progressLevels();

        $groepen = CourseAssessment::query()
            ->when($filters['from'] ?? null, fn ($q, $van) => $q->whereDate('assessed_on', '>=', $van))
            ->when($filters['to'] ?? null, fn ($q, $tot) => $q->whereDate('assessed_on', '<=', $tot))
            ->with(['product', 'player'])
            ->get()
            ->filter(fn (CourseAssessment $a) => $a->product !== null && $a->player !== null)
            ->groupBy(fn (CourseAssessment $a) => $a->product_id.'-'.$a->player_id)
            ->sortBy(fn ($rijen) => $rijen->first()->product->name.' '.$rijen->first()->player->full_name);

        foreach ($groepen as $rijen) {
            $begin = $rijen->firstWhere('moment', CourseAssessment::BEGIN);
            $eind = $rijen->firstWhere('moment', CourseAssessment::EIND);
            $speler = $rijen->first()->player;

            $niveaus = collect($speler->position->categories())
                ->map(function (ReportCategory $c) use ($begin, $eind, $levels) {
                    $van = $begin ? CourseProgress::level($begin->levels[$c->value] ?? null, $begin->scale, $levels) : null;
                    $naar = $eind ? CourseProgress::level($eind->levels[$c->value] ?? null, $eind->scale, $levels) : null;

                    if ($van === null && $naar === null) {
                        return null;
                    }

                    return $c->label().': '.($van['label'] ?? '-').' → '.($naar['label'] ?? '-');
                })
                ->filter()
                ->implode('; ');

            yield [
                $rijen->first()->product->name,
                $speler->full_name,
                $begin?->assessed_on->format('d-m-Y'),
                $eind?->assessed_on->format('d-m-Y'),
                $niveaus,
                trim(implode("\n", array_filter([$begin?->note, $eind?->note]))) ?: null,
            ];
        }
    }
}
