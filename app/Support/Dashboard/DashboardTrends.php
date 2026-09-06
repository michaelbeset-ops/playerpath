<?php

namespace App\Support\Dashboard;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Report;
use Illuminate\Support\Carbon;

/**
 * De vier kerncijfers, elk met wat het vorige maand was.
 *
 * Een getal zonder vergelijking zegt weinig: "drie spelers" kan geweldig of
 * rampzalig zijn. Daarom staat er bij elk cijfer wat het deed.
 *
 * Twee afspraken:
 *
 * - **De vergelijking gaat over dezelfde lengte.** Deze maand tot vandaag
 *   tegenover vorige maand tot dezelfde dag; anders vergelijk je twee weken met
 *   een hele maand en lijkt alles altijd te dalen.
 * - **Null is geen nul.** Zonder rapporten is er geen gemiddelde rating, en dan
 *   staat er een streepje en geen trend. Een verzonnen nul is erger dan niets.
 */
class DashboardTrends
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $nu = now();
        $vorigePeriode = $nu->copy()->subMonthNoOverflow();

        return [
            'players' => $this->spelers($nu, $vorigePeriode),
            'rating' => $this->rating($nu, $vorigePeriode),
            'reports' => $this->rapporten($nu),
            'revenue' => $this->omzet($nu, $vorigePeriode),
        ];
    }

    /**
     * Actieve spelers nu, en hoeveel erbij kwamen sinds het begin van de maand.
     *
     * @return array<string, mixed>
     */
    protected function spelers(Carbon $nu, Carbon $vorige): array
    {
        $nuActief = Player::active()->count();

        $nieuw = Player::active()
            ->whereDate('created_at', '>=', $nu->copy()->startOfMonth()->toDateString())
            ->count();

        return [
            'value' => $nuActief,
            'change' => $nieuw,
            'unit' => 'aantal',
            'hint' => $nieuw === 0 ? 'geen nieuwe deze maand' : ($nieuw === 1 ? '1 erbij deze maand' : "{$nieuw} erbij deze maand"),
        ];
    }

    /**
     * Het gemiddelde rapportcijfer van deze maand tegenover vorige maand.
     *
     * Uit de rapporten en niet uit `players.overall_rating`: dat veld heeft geen
     * historie, dus daarmee valt geen "vorige maand" te maken.
     *
     * @return array<string, mixed>
     */
    protected function rating(Carbon $nu, Carbon $vorige): array
    {
        $dezeMaand = $this->gemiddeldeInPeriode($nu->copy()->startOfMonth(), $nu);
        $vorigeMaand = $this->gemiddeldeInPeriode($vorige->copy()->startOfMonth(), $vorige);

        return [
            'value' => $dezeMaand,
            'change' => $dezeMaand !== null && $vorigeMaand !== null ? $dezeMaand - $vorigeMaand : null,
            'unit' => 'punten',
            'hint' => $dezeMaand === null
                ? 'nog geen rapporten deze maand'
                : ($vorigeMaand === null ? 'geen vergelijking met vorige maand' : "vorige maand {$vorigeMaand}"),
        ];
    }

    protected function gemiddeldeInPeriode(Carbon $van, Carbon $tot): ?int
    {
        $rapporten = Report::query()
            ->whereDate('reported_on', '>=', $van->toDateString())
            ->whereDate('reported_on', '<=', $tot->toDateString())
            ->with('scores')
            ->get();

        $cijfers = $rapporten
            ->map(fn (Report $rapport) => $rapport->scores->isEmpty() ? null : $rapport->scores->avg('score') * 10)
            ->filter();

        return $cijfers->isEmpty() ? null : (int) round($cijfers->avg());
    }

    /**
     * Rapporten deze week tegenover vorige week.
     *
     * Per week en niet per maand: een trainer werkt in weken, en "vier
     * rapporten deze maand" op de derde van de maand zegt niets.
     *
     * @return array<string, mixed>
     */
    protected function rapporten(Carbon $nu): array
    {
        $dezeWeek = Report::query()
            ->whereDate('reported_on', '>=', $nu->copy()->startOfWeek()->toDateString())
            ->count();

        $vorigeWeek = Report::query()
            ->whereDate('reported_on', '>=', $nu->copy()->subWeek()->startOfWeek()->toDateString())
            ->whereDate('reported_on', '<=', $nu->copy()->subWeek()->endOfWeek()->toDateString())
            ->count();

        return [
            'value' => $dezeWeek,
            'change' => $dezeWeek - $vorigeWeek,
            'unit' => 'aantal',
            'hint' => "vorige week {$vorigeWeek}",
        ];
    }

    /**
     * Wat er deze maand binnenkwam, tegenover dezelfde dag vorige maand.
     *
     * @return array<string, mixed>
     */
    protected function omzet(Carbon $nu, Carbon $vorige): array
    {
        $dezeMaand = $this->ontvangen($nu->copy()->startOfMonth(), $nu);
        $vorigeMaand = $this->ontvangen($vorige->copy()->startOfMonth(), $vorige);

        $verschil = $vorigeMaand === 0
            ? null
            : (int) round(($dezeMaand - $vorigeMaand) / $vorigeMaand * 100);

        return [
            'cents' => $dezeMaand,
            'change' => $verschil,
            'unit' => 'procent',
            'previousCents' => $vorigeMaand,
            'hint' => $vorigeMaand === 0
                ? 'geen vergelijking met vorige maand'
                : 'tot dezelfde dag vorige maand',
        ];
    }

    protected function ontvangen(Carbon $van, Carbon $tot): int
    {
        return (int) Payment::where('status', PaymentStatus::Paid->value)
            ->whereDate('paid_at', '>=', $van->toDateString())
            ->whereDate('paid_at', '<=', $tot->toDateString())
            ->sum('amount_cents');
    }
}
