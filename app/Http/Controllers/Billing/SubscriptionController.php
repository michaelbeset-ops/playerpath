<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\GeneratePayments;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Player;
use App\Models\Subscription;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Abonnementen van spelers.
 *
 * Zolang er geen provider is, is dit administratie: je legt vast wie waarop
 * zit en voor hoeveel. Zodra Mollie eraan hangt, wordt hier ook de incasso of
 * iDEAL-betaling gestart — dat is precies de plek waar de PaymentGateway
 * straks meer methodes krijgt.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected GeneratePayments $facturen,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Subscription::class);

        $subscriptions = Subscription::query()
            ->with(['player', 'plan'])
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (Subscription $abonnement) => [
                'id' => $abonnement->id,
                'player' => $abonnement->player?->full_name,
                'player_id' => $abonnement->player_id,
                'plan' => $abonnement->plan?->name,
                'amount' => Money::format($abonnement->amount_cents),
                'interval' => $abonnement->interval->label(),
                'status' => $abonnement->status->value,
                'status_label' => $abonnement->status->label(),
                'method' => $abonnement->payment_method?->label(),
                'starts_on' => $abonnement->starts_on->format('d-m-Y'),
                'ends_on' => $abonnement->ends_on?->format('d-m-Y'),
            ]);

        return Inertia::render('billing/Subscriptions', [
            'subscriptions' => $subscriptions,
            'gateway' => $this->gatewayProps(),
            'playersWithoutSubscription' => Player::active()
                ->whereDoesntHave('subscriptions', fn ($q) => $q->active())
                ->orderBy('first_name')
                ->get()
                ->map(fn (Player $speler) => ['id' => $speler->id, 'name' => $speler->full_name]),
            'plans' => Plan::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'amount' => Money::format($plan->amount_cents),
                    'interval' => $plan->interval->label(),
                ]),
            'methods' => PaymentMethod::options(),
            'statuses' => SubscriptionStatus::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Subscription::class);

        $schoolId = app(Tenancy::class)->id();

        $validated = $request->validate([
            // exists kent de global scope niet, dus expliciet op school begrenzen.
            'player_id' => ['required', 'integer', Rule::exists('players', 'id')->where('school_id', $schoolId)],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('school_id', $schoolId)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'starts_on' => ['required', 'date'],
            // Een jaarbedrag in tien maandtermijnen is bij sportclubs normaal.
            'installments' => ['nullable', 'integer', 'between:1,12'],
        ], [
            'installments.between' => 'Kies één tot twaalf termijnen.',
        ], [
            'player_id' => 'De speler',
            'plan_id' => 'De abonnementsvorm',
            'payment_method' => 'De betaalmethode',
            'starts_on' => 'De ingangsdatum',
            'installments' => 'Het aantal termijnen',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Bedrag en frequentie worden overgenomen, niet gekoppeld: verandert de
        // school later haar tarief, dan verandert een lopend abonnement niet mee.
        $abonnement = Subscription::create([
            'player_id' => $validated['player_id'],
            'plan_id' => $plan->id,
            'amount_cents' => $plan->amount_cents,
            'interval' => $plan->interval,
            'installments' => ($validated['installments'] ?? 1) > 1 ? $validated['installments'] : null,
            'status' => SubscriptionStatus::Active,
            'payment_method' => $validated['payment_method'],
            'starts_on' => $validated['starts_on'],
        ]);

        // Meteen de rekening voor de lopende termijn; anders staat er tot de
        // nachtelijke facturenloop niets open en valt er dus niets te betalen.
        $aangemaakt = count($this->facturen->handle($abonnement));

        $melding = $this->gateway->isConnected()
            ? "Het abonnement is aangemaakt en er staat {$aangemaakt} betaling(en) klaar voor het gezin."
            : 'Het abonnement is vastgelegd. Er wordt nog niets geïncasseerd: '.$this->gateway->name().' is niet aangesloten.';

        return back()->with('status', $melding);
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
        ], [], ['status' => 'De status']);

        $nieuw = SubscriptionStatus::from($validated['status']);

        $subscription->update([
            'status' => $nieuw,
            'ends_on' => $nieuw === SubscriptionStatus::Cancelled ? ($subscription->ends_on ?? now()->toDateString()) : null,
        ]);

        return back()->with('status', "Het abonnement staat nu op '{$nieuw->label()}'.");
    }

    /** @return array<string, mixed> */
    protected function gatewayProps(): array
    {
        return [
            'connected' => $this->gateway->isConnected(),
            'name' => $this->gateway->name(),
            'message' => $this->gateway->statusMessage(),
        ];
    }
}
