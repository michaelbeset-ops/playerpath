<?php

namespace App\Http\Controllers\Billing;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Money\Money;
use App\Support\Payments\BillingOverview;
use App\Support\Payments\PaymentGateway;
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
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $filters = [
            'status' => (string) $request->string('status'),
            'search' => trim((string) $request->string('search')),
        ];

        $payments = Payment::query()
            ->with('player')
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $term = '%'.$filters['search'].'%';

                $query->whereHas('player', fn ($p) => $p
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            })
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'player' => $payment->player?->full_name,
                'player_id' => $payment->player_id,
                'amount' => Money::format($payment->amount_cents),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'method' => $payment->method?->label(),
                'description' => $payment->description,
                'due_on' => $payment->due_on->format('d-m-Y'),
                'paid_at' => $payment->paid_at?->format('d-m-Y'),
                'is_overdue' => $payment->isOverdue(),
            ]);

        return Inertia::render('billing/Payments', [
            'payments' => $payments,
            'filters' => $filters,
            'statuses' => PaymentStatus::options(),
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
        ], [], ['status' => 'De status']);

        $nieuw = PaymentStatus::from($validated['status']);

        $payment->update([
            'status' => $nieuw,
            'paid_at' => $nieuw === PaymentStatus::Paid ? ($payment->paid_at ?? now()) : null,
        ]);

        return back()->with('status', "De betaling staat nu op '{$nieuw->label()}'.");
    }
}
