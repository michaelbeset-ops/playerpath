<?php

namespace App\Support\Exports;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\EffortRating;
use App\Models\XpEvent;
use App\Support\Rating\RatingSettings;
use App\Support\Tenancy\Tenancy;

/**
 * Eén regel per kind per training: aanwezig, inzet, houding, punten en notitie.
 *
 * Het rapportenoverzicht van de inzetkaart. De punten zijn wat er echt geboekt
 * is (aanwezig plus inzet), niet wat de puntenwaarden vandaag zouden geven.
 */
class EffortExport implements Export, FormattedExport
{
    public function key(): string
    {
        return 'inzet';
    }

    public function title(): string
    {
        return 'Inzetpunten';
    }

    public function description(): string
    {
        return 'Elke training in een periode: wie er was, de inzet en houding, de punten en de notitie van de trainer.';
    }

    public function supportsDateRange(): bool
    {
        return true;
    }

    public function headings(): array
    {
        return ['Datum', 'Training', 'Speler', 'Aanwezig', 'Inzet', 'Houding', 'Punten', 'Notitie'];
    }

    public function types(): array
    {
        return ['date', 'text', 'text', 'text', 'text', 'text', 'number', 'text'];
    }

    public function totals(): array
    {
        return [];
    }

    public function rows(array $filters): iterable
    {
        $settings = RatingSettings::for(app(Tenancy::class)->school());

        $aanwezigheden = Attendance::query()
            ->whereNotNull('status')
            ->whereHas('training', function ($q) use ($filters) {
                $q->when($filters['from'] ?? null, fn ($q, $van) => $q->whereDate('starts_at', '>=', $van))
                    ->when($filters['to'] ?? null, fn ($q, $tot) => $q->whereDate('starts_at', '<=', $tot));
            })
            ->with(['training', 'player'])
            ->get()
            ->filter(fn (Attendance $a) => $a->training !== null && $a->player !== null)
            ->sortBy(fn (Attendance $a) => $a->training->starts_at->format('Y-m-d H:i').' '.$a->player->full_name);

        $inzet = EffortRating::query()
            ->whereIn('training_id', $aanwezigheden->pluck('training_id')->unique())
            ->get()
            ->keyBy(fn (EffortRating $r) => $r->training_id.'-'.$r->player_id);

        $basis = XpEvent::query()
            ->where('source', 'attendance')
            ->where('reference_type', Attendance::class)
            ->whereIn('reference_id', $aanwezigheden->pluck('id'))
            ->pluck('points', 'reference_id');

        foreach ($aanwezigheden as $aanwezigheid) {
            $rating = $inzet->get($aanwezigheid->training_id.'-'.$aanwezigheid->player_id);
            $aanwezig = $aanwezigheid->status === AttendanceStatus::Present;

            yield [
                $aanwezigheid->training->starts_at->format('d-m-Y'),
                $aanwezigheid->training->label(),
                $aanwezigheid->player->full_name,
                $aanwezig ? 'Ja' : 'Nee',
                $rating ? ($settings->effortLevel($rating->effort)['label'] ?? null) : null,
                $rating ? ($settings->attitudeLevel($rating->attitude)['label'] ?? null) : null,
                $aanwezig ? (int) ($basis[$aanwezigheid->id] ?? 0) + ($rating?->points() ?? 0) : 0,
                $rating?->note,
            ];
        }
    }
}
