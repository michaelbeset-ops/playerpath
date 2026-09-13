<?php

namespace App\Support\Exports;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Het financiële werkboek: vier tabbladen die samen het verhaal vertellen.
 *
 * 1. Overzicht    - per maand: ontvangen, openstaand, aantallen
 * 2. Betalingen   - elke betaling, ook mislukt en gestorneerd
 * 3. Openstaand   - wat er nog binnen moet komen, met contactgegevens erbij
 * 4. Abonnementen - wie zit op wat, en wat is dat op jaarbasis waard
 *
 * Bedragen als getal in euro's (12.5), uit centen. Nooit als tekst met
 * euroteken - dan kan Excel er niet mee optellen. Zie Export.
 */
class FinancialExport implements WorkbookExport
{
    public function key(): string
    {
        return 'financial';
    }

    public function title(): string
    {
        return 'Financieel';
    }

    public function description(): string
    {
        return 'Ontvangen en openstaande betalingen per maand, alle betalingen, wie nog moet betalen en de lopende abonnementen. Vier tabbladen in Excel.';
    }

    public function supportsDateRange(): bool
    {
        return true;
    }

    /** CSV krijgt alleen het hoofdtabblad: de betalingen. */
    public function headings(): array
    {
        return ['Vervaldatum', 'Betaald op', 'Speler', 'Omschrijving', 'Bedrag', 'Status', 'Methode', 'Maand'];
    }

    /** @return list<string> */
    protected function types(): array
    {
        return ['date', 'date', 'text', 'text', 'money', 'text', 'text', 'text'];
    }

    public function rows(array $filters): iterable
    {
        foreach ($this->betalingen($filters) as $betaling) {
            yield [
                $betaling->due_on->format('d-m-Y'),
                $betaling->paid_at?->format('d-m-Y'),
                $betaling->player?->full_name,
                $betaling->description,
                self::euro($betaling->amount_cents),
                $betaling->status->label(),
                $betaling->method?->label(),
                $betaling->due_on->format('Y-m'),
            ];
        }
    }

    public function sheets(array $filters): array
    {
        return [
            // Het overzicht telt zelf al op (de regel "Totaal"); de andere
            // tabbladen krijgen hun totaal van de writer.
            new Sheet(
                'Overzicht',
                ['Maand', 'Ontvangen', 'Openstaand', 'Mislukt of gestorneerd', 'Aantal betalingen', 'Aantal betaald'],
                $this->overzicht($filters),
                ['text', 'money', 'money', 'money', 'int', 'int'],
            ),
            new Sheet('Betalingen', $this->headings(), $this->rows($filters), $this->types(), [4]),
            new Sheet(
                'Openstaand',
                ['Speler', 'Ouder(s)', 'E-mail', 'Omschrijving', 'Bedrag', 'Vervaldatum', 'Dagen te laat', 'Status'],
                $this->openstaand(),
                ['text', 'text', 'text', 'text', 'money', 'date', 'int', 'text'],
                [4],
            ),
            new Sheet(
                'Abonnementen',
                ['Speler', 'Tarief', 'Bedrag', 'Frequentie', 'Status', 'Betaalmethode', 'Sinds', 'Tot', 'Jaarwaarde'],
                $this->abonnementen(),
                ['text', 'text', 'money', 'text', 'text', 'text', 'date', 'date', 'money'],
                [2, 8],
            ),
        ];
    }

    /** @return Collection<int, Payment> */
    protected function betalingen(array $filters)
    {
        return Payment::query()
            ->with('player')
            ->when($filters['from'] ?? null, fn ($q, $van) => $q->where('due_on', '>=', $van))
            ->when($filters['to'] ?? null, fn ($q, $tot) => $q->where('due_on', '<=', $tot))
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();
    }

    /** Per maand, met een totaalregel onderaan. */
    protected function overzicht(array $filters): iterable
    {
        $betalingen = $this->betalingen($filters);

        $perMaand = $betalingen->groupBy(fn (Payment $b) => $b->due_on->format('Y-m'))->sortKeys();

        $totaalOntvangen = 0;
        $totaalOpen = 0;
        $totaalMis = 0;

        foreach ($perMaand as $maand => $items) {
            $ontvangen = (int) $items->where('status', PaymentStatus::Paid)->sum('amount_cents');
            $open = (int) $items->where('status', PaymentStatus::Open)->sum('amount_cents');
            $mis = (int) $items->filter(fn (Payment $b) => $b->status->needsAttention())->sum('amount_cents');

            $totaalOntvangen += $ontvangen;
            $totaalOpen += $open;
            $totaalMis += $mis;

            yield [
                ucfirst(CarbonImmutable::parse($maand.'-01')->translatedFormat('F Y')),
                self::euro($ontvangen),
                self::euro($open),
                self::euro($mis),
                $items->count(),
                $items->where('status', PaymentStatus::Paid)->count(),
            ];
        }

        yield [
            'Totaal',
            self::euro($totaalOntvangen),
            self::euro($totaalOpen),
            self::euro($totaalMis),
            $betalingen->count(),
            $betalingen->where('status', PaymentStatus::Paid)->count(),
        ];
    }

    /** Alles wat nog binnen moet komen, met wie je daarvoor moet bellen. */
    protected function openstaand(): iterable
    {
        $rijen = Payment::outstanding()
            ->with('player.guardians')
            ->orderBy('due_on')
            ->get();

        foreach ($rijen as $betaling) {
            $teLaat = $betaling->due_on->isPast() ? (int) $betaling->due_on->diffInDays(now()) : 0;

            yield [
                $betaling->player?->full_name,
                $betaling->player?->guardians->pluck('name')->implode(', '),
                $betaling->player?->guardians->pluck('email')->implode(', '),
                $betaling->description,
                self::euro($betaling->amount_cents),
                $betaling->due_on->format('d-m-Y'),
                $teLaat,
                $betaling->status->label(),
            ];
        }
    }

    protected function abonnementen(): iterable
    {
        $rijen = Subscription::query()
            ->with(['player', 'product'])
            ->orderBy('status')
            ->orderBy('starts_on')
            ->get();

        foreach ($rijen as $abonnement) {
            yield [
                $abonnement->player?->full_name,
                $abonnement->product?->name,
                self::euro($abonnement->amount_cents),
                $abonnement->interval->label(),
                $abonnement->status->label(),
                $abonnement->payment_method?->label(),
                $abonnement->starts_on->format('d-m-Y'),
                $abonnement->ends_on?->format('d-m-Y'),
                self::euro($abonnement->yearlyValueCents()),
            ];
        }
    }

    /** Centen naar een getal in euro's, alleen hier aan de rand. */
    protected static function euro(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
