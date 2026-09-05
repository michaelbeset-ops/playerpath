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
    public function summary(): array
    {
        $betaaldDezeMaand = (int) Payment::paid()
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_cents');

        $openstaand = (int) Payment::outstanding()->sum('amount_cents');

        $achterstallig = Payment::where('status', PaymentStatus::Open->value)
            ->where('due_on', '<', now()->toDateString());

        $abonnementen = Subscription::active()->get(['amount_cents', 'interval']);

        $perJaar = $abonnementen->sum(fn (Subscription $abonnement) => $abonnement->yearlyValueCents());

        return [
            'revenueThisMonth' => Money::format($betaaldDezeMaand),
            'revenueThisMonthCents' => $betaaldDezeMaand,

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
