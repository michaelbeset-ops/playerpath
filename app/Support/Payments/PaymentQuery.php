<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * De filters van het financiële overzicht, op één plek.
 *
 * De lijst en het totaal onder de kop moeten dezelfde rijen tellen. Stonden de
 * filters twee keer, dan wijzen ze vroeg of laat naar iets anders en klopt het
 * totaal niet meer met wat je eronder ziet - precies het soort verschil waar
 * niemand een boekhouder mee wil laten bellen.
 */
class PaymentQuery
{
    /** De perioden die je kunt kiezen, in de volgorde waarin je ze gebruikt. */
    public const PERIODEN = [
        'this_month' => 'Deze maand',
        'last_month' => 'Vorige maand',
        'this_quarter' => 'Dit kwartaal',
        'this_year' => 'Dit jaar',
        'all' => 'Alles',
    ];

    /**
     * De tabbladen. Bewust vijf, en elk beantwoordt één vraag: wat kwam er
     * binnen, wat staat er open, wat is te laat, wat komt eraan, en alles.
     */
    public const TABBLADEN = [
        'paid' => 'Ontvangen',
        'open' => 'Openstaand',
        'overdue' => 'Te laat',
        'planned' => 'Gepland',
        'all' => 'Alles',
    ];

    /**
     * @param  array{tab: string, period: string, search: string, method: string, product?: int|null}  $filters
     */
    public function build(array $filters): Builder
    {
        $query = Payment::query()->with(['player', 'purchase']);

        $this->periode($query, $filters['period'], $filters['tab']);
        $this->tabblad($query, $filters['tab']);

        if ($filters['method'] !== '') {
            $query->where('method', $filters['method']);
        }

        // Filteren op één aanbod: "wie heeft het zomerkamp al betaald?" is de
        // vraag die een school stelt, en die gaat over een kamp en niet over
        // een maand. De rekening hangt aan een aankoop of aan een abonnement;
        // allebei wijzen ze naar het aanbod.
        if (! empty($filters['product'])) {
            $aanbod = (int) $filters['product'];

            $query->where(fn (Builder $q) => $q
                ->whereHas('purchase', fn (Builder $p) => $p->where('product_id', $aanbod))
                ->orWhereHas('subscription', fn (Builder $a) => $a->where('product_id', $aanbod)));
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';

            $query->where(fn ($q) => $q
                ->where('description', 'like', $term)
                ->orWhereHas('player', fn ($p) => $p
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)));
        }

        return $query;
    }

    /**
     * Op welke datum de periode filtert.
     *
     * Dit verschilt per tabblad, en dat is geen detail: "ontvangen in maart"
     * gaat over de dag dat het geld binnenkwam, "openstaand in maart" over de
     * dag dat het had moeten binnenkomen.
     */
    public function datumkolom(string $tab): string
    {
        return $tab === 'paid' ? 'paid_at' : 'due_on';
    }

    protected function periode(Builder $query, string $periode, string $tab): void
    {
        $bereik = self::range($periode);

        if ($bereik === null) {
            return;
        }

        [$van, $tot] = $bereik;
        $kolom = $this->datumkolom($tab);

        // whereDate en niet whereBetween op strings: deze kolommen dragen een
        // tijdcomponent, en dan valt de laatste dag lexicografisch buiten de
        // boot. Zie de valkuil bij reported_on in CLAUDE.md.
        $query->whereDate($kolom, '>=', $van->toDateString())
            ->whereDate($kolom, '<=', $tot->toDateString());
    }

    protected function tabblad(Builder $query, string $tab): void
    {
        match ($tab) {
            'paid' => $query->where('status', PaymentStatus::Paid->value),
            'open' => $query->where('status', PaymentStatus::Open->value),
            // Contant bij de training is geen achterstand; zie Payment::isCashAtTraining().
            'overdue' => $query->where('status', PaymentStatus::Open->value)
                ->whereDate('due_on', '<', now()->toDateString())
                ->whereNot(fn (Builder $q) => $q->whereNotNull('training_enrollment_id')->where('method', 'cash')),
            'planned' => $query->where('status', PaymentStatus::Open->value)
                ->whereDate('due_on', '>', now()->toDateString()),
            default => null,
        };
    }

    /**
     * Het totaal onder de kop: aantal, bedrag en het bedrag exclusief btw.
     *
     * Ex btw wordt **per rij** berekend en niet over het totaal, want de
     * tarieven verschillen per product. Negen procent over een rittenkaart en
     * eenentwintig over een shirt bij elkaar optellen en er één percentage
     * vanaf halen geeft een getal dat nergens op slaat.
     *
     * @return array{count: int, total: int, excl_vat: int, vat: int}
     */
    public function totals(Builder $query): array
    {
        $rijen = (clone $query)->reorder()->get(['amount_cents', 'vat_rate']);

        $totaal = (int) $rijen->sum('amount_cents');
        $exclusief = (int) $rijen->sum(fn (Payment $p) => (int) round($p->amount_cents / (1 + $p->vat_rate / 100)));

        return [
            'count' => $rijen->count(),
            'total' => $totaal,
            'excl_vat' => $exclusief,
            'vat' => $totaal - $exclusief,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    /**
     * De periode als woorden voor op het scherm: "september 2026", niet
     * "this_month". Zo staat er bij elk cijfer waarover het gaat.
     */
    public static function label(string $periode): string
    {
        return match ($periode) {
            'all' => 'alle tijd',
            'last_month' => now()->subMonthNoOverflow()->translatedFormat('F Y'),
            'this_quarter' => 'kwartaal '.now()->quarter.' van '.now()->year,
            'this_year' => (string) now()->year,
            default => now()->translatedFormat('F Y'),
        };
    }

    public static function range(string $periode): ?array
    {
        return match ($periode) {
            'all' => null,
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * Een keuze uit de URL veilig terugbrengen tot iets dat bestaat.
     *
     * @param  array<string, string>  $toegestaan
     */
    public static function kies(?string $waarde, array $toegestaan, string $standaard): string
    {
        return array_key_exists((string) $waarde, $toegestaan) ? (string) $waarde : $standaard;
    }
}
