<?php

namespace App\Support\Exports;

use App\Enums\AttendanceStatus;
use App\Models\Training;

class TrainingsExport implements Export
{
    public function key(): string
    {
        return 'trainings';
    }

    public function title(): string
    {
        return 'Trainingen';
    }

    public function description(): string
    {
        return 'Alle trainingen in een periode, met groep, trainers, locatie en de opkomst per training.';
    }

    public function supportsDateRange(): bool
    {
        return true;
    }

    public function headings(): array
    {
        return ['Datum', 'Van', 'Tot', 'Groep', 'Trainers', 'Locatie', 'Verwacht', 'Aanwezig', 'Afwezig', 'Niet afgevinkt', 'Toelichting'];
    }

    public function rows(array $filters): iterable
    {
        $trainingen = Training::query()
            ->with(['group', 'trainers', 'attendances'])
            ->when($filters['from'] ?? null, fn ($q, $van) => $q->where('starts_at', '>=', $van))
            ->when($filters['to'] ?? null, fn ($q, $tot) => $q->where('starts_at', '<=', $tot.' 23:59:59'))
            ->orderBy('starts_at')
            ->get();

        foreach ($trainingen as $training) {
            $verwacht = $training->expectedPlayers()->count();
            $aanwezig = $training->attendances->where('status', AttendanceStatus::Present)->count();
            $afwezig = $training->attendances->where('status', AttendanceStatus::Absent)->count();

            yield [
                $training->starts_at->format('d-m-Y'),
                $training->starts_at->format('H:i'),
                $training->ends_at->format('H:i'),
                $training->label(),
                $training->trainers->pluck('name')->implode(', '),
                $training->location,
                $verwacht,
                $aanwezig,
                $afwezig,
                max(0, $verwacht - $aanwezig - $afwezig),
                $training->note,
            ];
        }
    }
}
