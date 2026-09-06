<?php

namespace App\Http\Controllers\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Money\Money;
use App\Support\Payments\BillingOverview;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het betaaloverzicht van de school.
 *
 * Zolang er geen betaalprovider is aangesloten maakt de app zelf geen
 * betalingen aan. Wat je hier ziet komt uit de administratie; de eigenaar kan
 * een betaling met de hand op betaald zetten, bijvoorbeeld na een overboeking.
 */
class PaymentController extends Controller
{
    public function __construct(
        protected BillingOverview $overview,
        protected PaymentGateway $gateway,
        protected PaymentQuery $filter,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $filters = [
            'tab' => PaymentQuery::kies($request->string('tab'), PaymentQuery::TABBLADEN, 'all'),
            'period' => PaymentQuery::kies($request->string('period'), PaymentQuery::PERIODEN, 'this_month'),
            'method' => (string) $request->string('method'),
            'search' => trim((string) $request->string('search')),
        ];

        $query = $this->filter->build($filters);

        // Het totaal telt precies de rijen die je eronder ziet; daarom eerst
        // optellen en pas daarna de lijst afkappen.
        $totalen = $this->filter->totals($query);

        $payments = (clone $query)
            ->orderByDesc($this->filter->datumkolom($filters['tab']))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'player' => $payment->player?->full_name,
                'player_id' => $payment->player_id,
                'amount' => Money::format($payment->amount_cents),
                'vat_rate' => $payment->vat_rate,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'method' => $payment->method?->label(),
                'method_value' => $payment->method?->value,
                'description' => $payment->description,
                'due_on' => $payment->due_on->format('d-m-Y'),
                'paid_at' => $payment->paid_at?->format('d-m-Y'),
                'is_overdue' => $payment->isOverdue(),
            ]);

        return Inertia::render('billing/Payments', [
            'payments' => $payments,
            'filters' => $filters,
            'tabs' => PaymentQuery::TABBLADEN,
            'periods' => PaymentQuery::PERIODEN,
            'totals' => [
                'count' => $totalen['count'],
                'total' => Money::format($totalen['total']),
                'excl_vat' => Money::format($totalen['excl_vat']),
                'vat' => Money::format($totalen['vat']),
                // Meer dan er getoond worden: dan is de lijst afgekapt en moet
                // het scherm dat zeggen, anders lijkt het totaal niet te kloppen.
                'shown' => min($totalen['count'], 200),
            ],
            'statuses' => PaymentStatus::options(),
            'methods' => PaymentMethod::options(),
            'summary' => $this->overview->summary(),
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
                'message' => $this->gateway->statusMessage(),
            ],
        ]);
    }

    /**
     * De status van een betaling met de hand aanpassen.
     *
     * Nodig zolang er geen provider is, en daarna nog steeds voor het geval
     * iemand contant of per overboeking betaalt.
     */
    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(PaymentStatus::class)],
            // Hoe het binnenkwam. Bij contant en overboeking is dit de enige
            // plek waar dat vastgelegd wordt: dat geld komt buiten het systeem
            // om binnen, en zonder dit veld weet je later niet meer waar het
            // vandaan kwam.
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ], [], ['status' => 'De status', 'method' => 'De betaalmethode']);

        $nieuw = PaymentStatus::from($validated['status']);
        $methode = isset($validated['method']) ? PaymentMethod::from($validated['method']) : null;

        $payment->update([
            'status' => $nieuw,
            'paid_at' => $nieuw === PaymentStatus::Paid ? ($payment->paid_at ?? now()) : null,
            'method' => $methode ?? $payment->method,
        ]);

        $melding = $methode !== null && $nieuw === PaymentStatus::Paid
            ? "De betaling staat op betaald ({$methode->label()})."
            : "De betaling staat nu op '{$nieuw->label()}'.";

        return back()->with('status', $melding);
    }
}
