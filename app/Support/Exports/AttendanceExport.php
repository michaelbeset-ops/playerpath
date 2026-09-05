<?php

namespace App\Support\Exports;

use App\Models\Attendance;

/**
 * Eén regel per speler per training. Grof, maar precies wat je in Excel wilt
 * om zelf te draaien en te filteren.
 */
class AttendanceExport implements Export
{
    public function key(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Aanwezigheid';
    }

    public function description(): string
    {
        return 'Per speler per training: aangemeld of afgemeld, en wat de trainer afvinkte.';
    }

    public function supportsDateRange(): bool
    {
        return true;
    }

    public function headings(): array
    {
        return ['Datum', 'Tijd', 'Groep', 'Speler', 'Positie', 'Aanmelding', 'Aanwezigheid'];
    }

    public function rows(array $filters): iterable
    {
        $rijen = Attendance::query()
            ->with(['training.group', 'player'])
            ->whereHas('training', fn ($q) => $q
                ->when($filters['from'] ?? null, fn ($t, $van) => $t->where('starts_at', '>=', $van))
                ->when($filters['to'] ?? null, fn ($t, $tot) => $t->where('starts_at', '<=', $tot.' 23:59:59')))
            ->get()
            ->filter(fn (Attendance $a) => $a->training !== null && $a->player !== null)
            ->sortBy([
                fn ($a, $b) => $a->training->starts_at <=> $b->training->starts_at,
                fn ($a, $b) => strcmp($a->player->last_name, $b->player->last_name),
            ]);

        foreach ($rijen as $rij) {
            yield [
                $rij->training->starts_at->format('d-m-Y'),
                $rij->training->starts_at->format('H:i'),
                $rij->training->group->name,
                $rij->player->full_name,
                $rij->player->position->label(),
                $rij->registration?->label() ?? '',
                $rij->status?->label() ?? 'Niet afgevinkt',
            ];
        }
    }
}
