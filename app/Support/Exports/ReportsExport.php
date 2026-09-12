<?php

namespace App\Support\Exports;

use App\Models\Report;
use App\Models\ReportScore;

/**
 * Eén regel per rapport: wie, wanneer, door wie, het gemiddelde en de cijfers.
 *
 * De categorieën verschillen per positie (keeper en veldspeler), dus ze staan
 * niet als losse kolommen maar als één leesbare kolom "Reflexen 7,5; …". Het
 * gemiddelde staat wél als getal, zodat je erop kunt sorteren en rekenen.
 */
class ReportsExport implements Export, FormattedExport
{
    public function key(): string
    {
        return 'reports';
    }

    public function title(): string
    {
        return 'Rapporten';
    }

    public function description(): string
    {
        return 'Elk rapport in een periode: speler, trainer, gemiddelde en de cijfers per categorie.';
    }

    public function supportsDateRange(): bool
    {
        return true;
    }

    public function headings(): array
    {
        return ['Datum', 'Speler', 'Positie', 'Trainer', 'Gemiddelde', 'Cijfers', 'Toelichting'];
    }

    public function types(): array
    {
        return ['date', 'text', 'text', 'text', 'number', 'text', 'text'];
    }

    public function totals(): array
    {
        return [];
    }

    public function rows(array $filters): iterable
    {
        $rapporten = Report::query()
            ->with(['player', 'trainer', 'scores'])
            ->when($filters['from'] ?? null, fn ($q, $van) => $q->whereDate('reported_on', '>=', $van))
            ->when($filters['to'] ?? null, fn ($q, $tot) => $q->whereDate('reported_on', '<=', $tot))
            ->orderBy('reported_on')
            ->orderBy('id')
            ->get();

        foreach ($rapporten as $rapport) {
            $cijfers = $rapport->scores
                ->sortBy(fn (ReportScore $s) => $s->category->value)
                ->map(fn (ReportScore $s) => $s->category->label().' '.number_format($s->score, 1, ',', ''))
                ->implode('; ');

            yield [
                $rapport->reported_on->format('d-m-Y'),
                $rapport->player?->full_name,
                $rapport->player?->position->label(),
                $rapport->trainer?->name,
                $rapport->scores->isEmpty() ? null : round((float) $rapport->scores->avg('score'), 1),
                $cijfers,
                $rapport->note,
            ];
        }
    }
}
