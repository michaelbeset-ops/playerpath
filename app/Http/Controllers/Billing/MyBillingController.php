<?php

namespace App\Http\Controllers\Billing;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Player;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Enrollment\RefundPolicy;
use App\Support\Money\Money;
use App\Support\Pagination\LoadMore;
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
    /** Twee jaar maandtermijnen; ouder staat achter "Oudere betalingen tonen". */
    public const PER_PAGINA = 24;

    public function __construct(protected PaymentGateway $gateway) {}

    public function index(Request $request): Response
    {
        // Rekeningen zijn van de ouder; een kind met een eigen inlog heeft
        // er niets aan en hoort ze ook niet te zien.
        abort_unless($request->user()->isOuder(), 403, 'Betalingen regelen je ouders.');

        $spelerIds = $request->user()->visiblePlayerIds();

        abort_if($spelerIds === [], 403, 'Je hebt geen spelers waar een abonnement bij hoort.');

        $spelers = Player::whereIn('id', $spelerIds)
            // Het lopende abonnement en zijn aanbod in één keer, niet per kind
            // een query. Dezelfde selectie als Player::activeSubscription().
            ->with(['subscriptions' => fn ($q) => $q->active()->latest('starts_on')->with('product')])
            ->orderBy('first_name')
            ->get()
            ->map(function (Player $speler) {
                $abonnement = $speler->subscriptions->first();
                $kanOpzeggen = $abonnement !== null && $abonnement->status->canTransitionTo(SubscriptionStatus::CancellationPlanned);

                return [
                    'id' => $speler->id,
                    'name' => $speler->full_name,
                    'subscription' => $abonnement ? [
                        'plan' => $abonnement->product?->name,
                        'amount' => Money::format($abonnement->amount_cents),
                        'interval' => $abonnement->interval->label(),
                        'method' => $abonnement->payment_method?->label(),
                        'status_label' => $abonnement->status->label(),
                        'starts_on' => $abonnement->starts_on->format('d-m-Y'),
                        'ends_on' => $abonnement->ends_on?->format('d-m-Y'),
                        'status' => $abonnement->status->value,
                        'id' => $abonnement->id,
                        'can_cancel' => $kanOpzeggen,
                    ] : null,
                ];
            });

        // Per pagina, niet stil afgekapt: een ouder die wil weten wat hij vorig
        // jaar betaalde moet dat kunnen vinden.
        $betalingenQuery = Payment::whereIn('player_id', $spelerIds)
            ->with('player')
            ->orderByDesc('due_on')
            ->orderByDesc('id');

        [$betalingen, $pagina] = LoadMore::paginate($betalingenQuery, $request, 'payments', self::PER_PAGINA, fn (Payment $betaling) => [
                'id' => $betaling->id,
                'player' => $betaling->player?->first_name,
                'amount' => Money::format($betaling->amount_cents),
                'status' => $betaling->status->value,
                // Contant bij de training is geen openstaande rekening maar een
                // afspraak; dat hoort het scherm zo te zeggen.
                'status_label' => $betaling->isCashAtTraining() && $betaling->status === PaymentStatus::Open
                    ? 'Contant te voldoen bij de training'
                    : $betaling->status->label(),
                'cash_at_training' => $betaling->isCashAtTraining(),
                'method_value' => $betaling->method?->value,
                'description' => $betaling->description,
                'due_on' => $betaling->due_on->format('d-m-Y'),
                'paid_at' => $betaling->paid_at?->format('d-m-Y'),
                'is_overdue' => $betaling->isOverdue(),
                // Alleen aanbieden wat echt kan: zonder provider is er niets
                // te betalen, en een voldane betaling hoort geen knop te hebben.
                //
                // Contant en overboeking krijgen bewust géén knop. Die worden
                // bij de school zelf afgerekend; online ook nog kunnen betalen
                // levert een gezin op dat twee keer betaalt, en dat terugdraaien
                // kost meer dan het gemak waard is.
                'payable' => $this->gateway->isConnected()
                    && $betaling->status->isPayable()
                    && ! ($betaling->method?->isOffline() ?? false),
                'offline' => $betaling->method?->isOffline() ?? false,
            ]);

        $openstaand = (int) Payment::whereIn('player_id', $spelerIds)->outstanding()->sum('amount_cents');

        // De inschrijvingen van de kinderen: wat loopt, en wat je nog kunt
        // annuleren volgens het restitutiebeleid.
        $instellingen = EnrollmentSettings::for($request->user()->school);
        $beleid = RefundPolicy::for($instellingen);

        $inschrijvingen = Enrollment::whereIn('player_id', $spelerIds)
            // De orderregels meteen mee: het restitutiebedrag hoeft dan niet per
            // inschrijving een eigen query.
            ->with(['product', 'order.payments', 'order.lines'])
            ->whereNotIn('status', [EnrollmentStatus::Declined->value, EnrollmentStatus::Expired->value])
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (Enrollment $e) use ($beleid) {
                $regel = $e->order?->lines
                    ->filter(fn ($l) => (int) $l->enrollment_id === $e->id && in_array($l->type?->value, ['offering', 'trial'], strict: true))
                    ->sum('amount_cents') ?? 0;
                $betaald = $e->order ? (int) $e->order->payments->filter(fn ($p) => $p->status->countsAsRevenue())->sum('amount_cents') : 0;
                $terug = $beleid->refundCents(max(0, min((int) $regel, $betaald)), $e->product?->starts_on);

                return [
                    'id' => $e->id,
                    'child' => $e->first_name,
                    'product' => $e->product?->name,
                    'starts_on' => $e->product?->starts_on?->format('d-m-Y'),
                    'ends_on' => $e->product?->ends_on?->format('d-m-Y'),
                    'status' => $e->status->value,
                    'status_label' => $e->status->label(),
                    'can_cancel' => $e->status->canTransitionTo(EnrollmentStatus::Cancelled) && ! $e->status->isOpen(),
                    'refund' => Money::format($terug),
                    'refund_cents' => $terug,
                    'is_free' => $beleid->isFree($e->product?->starts_on),
                ];
            })
            ->values();

        return Inertia::render('billing/MyBilling', [
            'enrollments' => $inschrijvingen,
            'policy' => ['cancellation' => $beleid->describe(), 'notice_months' => $instellingen->noticeMonths()],
            'players' => $spelers,
            'payments' => $betalingen,
            'paymentsPage' => $pagina,
            'outstanding' => Money::format($openstaand),
            'hasOutstanding' => $openstaand > 0,
            'gateway' => [
                'connected' => $this->gateway->isConnected(),
                'name' => $this->gateway->name(),
            ],
        ]);
    }
}
