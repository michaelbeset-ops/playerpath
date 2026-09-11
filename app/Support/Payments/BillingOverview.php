<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Money\Money;

/**
 * De financiële cijfers van een school.
 *
 * Alles wordt in centen gerekend en pas bij het teruggeven geformatteerd, zodat
 * er nergens met floats gerekend wordt. Zie CLAUDE.md 3.2.
 */
class BillingOverview
{
    /** @return array<string, mixed> */
    /**
     * @param  string  $periode  een sleutel uit PaymentQuery::PERIODEN; bepaalt
     *                           waarover "ontvangen" gaat. Openstaand en
     *                           achterstallig zijn een stand van nu en volgen
     *                           de periode bewust niet: wat openstaat, staat open.
     */
    public function summary(string $periode = 'this_month'): array
    {
        $ontvangen = Payment::paid();
        $bereik = PaymentQuery::range($periode);

        if ($bereik !== null) {
            [$van, $tot] = $bereik;
            // whereDate: paid_at draagt een tijd, en dan valt de laatste dag
            // anders buiten de boot (zie de valkuil bij reported_on in CLAUDE.md).
            $ontvangen->whereDate('paid_at', '>=', $van->toDateString())->whereDate('paid_at', '<=', $tot->toDateString());
        }

        $betaaldDezeMaand = (int) $ontvangen->sum('amount_cents');

        $openstaand = (int) Payment::outstanding()->sum('amount_cents');

        $achterstallig = Payment::where('status', PaymentStatus::Open->value)
            ->where('due_on', '<', now()->toDateString());

        $abonnementen = Subscription::active()->get(['amount_cents', 'interval']);

        $perJaar = $abonnementen->sum(fn (Subscription $abonnement) => $abonnement->yearlyValueCents());

        return [
            'revenueThisMonth' => Money::format($betaaldDezeMaand),
            'revenueThisMonthCents' => $betaaldDezeMaand,
            'period' => $periode,
            'periodLabel' => PaymentQuery::label($periode),

            'outstanding' => Money::format($openstaand),
            'outstandingCents' => $openstaand,
            'outstandingCount' => Payment::outstanding()->count(),

            'overdueCount' => (clone $achterstallig)->count(),
            'overdue' => Money::format((int) (clone $achterstallig)->sum('amount_cents')),

            'activeSubscriptions' => $abonnementen->count(),
            // Wat de lopende abonnementen op jaarbasis waard zijn. Een
            // vooruitblik, geen omzet: er is nog niets van betaald.
            'yearlyValue' => Money::format($perJaar),

            'needsAttentionCount' => Payment::whereIn('status', [
                PaymentStatus::Failed->value,
                PaymentStatus::ChargedBack->value,
            ])->count(),
        ];
    }
}
