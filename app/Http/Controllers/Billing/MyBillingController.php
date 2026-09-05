<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Subscription;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mijn abonnement" voor de ouder of speler.
 *
 * Alleen de eigen kinderen: de lijst met spelers komt uit visiblePlayerIds(),
 * dezelfde bron als de trainingen en de spelerskaart.
 */
class MyBillingController extends Controller
{
    public function __construct(protected PaymentGateway $gateway) {}

    public function index(Request $request): Response
    {
        $spelerIds = $request->user()->visiblePlayerIds();

        abort_if($spelerIds === [], 403, 'Je hebt geen spelers waar een abonnement bij hoort.');

        $spelers = Player::whereIn('id', $spelerIds)
            ->orderBy('first_name')
            ->get()
            ->map(function (Player $speler) {
                $abonnement = $speler->activeSubscription();

                return [
                    'id' => $speler->id,
                    'name' => $speler->full_name,
                    'subscription' => $abonnement ? [
                        'plan' => $abonnement->plan?->name,
                        'amount' => Money::format($abonnement->amount_cents),
                        'interval' => $abonnement->interval->label(),
                        'method' => $abonnement->payment_method?->label(),
                        'status_label' => $abonnement->status->label(),
                        'starts_on' => $abonnement->starts_on->format('d-m-Y'),
                    ] : null,
                ];
            });

        $betalingen = Payment::whereIn('player_id', $spelerIds)
            ->with('player')
            ->orderByDesc('due_on')
            ->limit(24)
            ->get()
            ->map(fn (Payment $betaling) => [
                'id' => $betaling->id,
                'player' => $betaling->player?->first_name,
                'amount' => Money::format($betaling->amount_cents),
                'status' => $betaling->status->value,
                'status_label' => $betaling->status->label(),
                'description' => $betaling->description,
                'due_on' => $betaling->due_on->format('d-m-Y'),
                'paid_at' => $betaling->paid_at?->format('d-m-Y'),
                'is_overdue' => $betaling->isOverdue(),
            ]);

        $openstaand = (int) Payment::whereIn('player_id', $spelerIds)->outstanding()->sum('amount_cents');

        return Inertia::render('billing/MyBilling', [
            'players' => $spelers,
            'payments' => $betalingen,
            'outstanding' => Money::format($openstaand),
            'hasOutstanding' => $openstaand > 0,
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
            ],
        ]);
    }
}
