<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\SettleOrder;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Product;
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
            'product' => $request->integer('product') ?: null,
            'search' => trim((string) $request->string('search')),
        ];

        $query = $this->filter->build($filters);

        // Het totaal telt precies de rijen die je eronder ziet; daarom eerst
        // optellen en pas daarna de lijst afkappen.
        $totalen = $this->filter->totals($query);

        // De lijst wordt per dag gegroepeerd, en welke dag dat is verschilt per
        // tabblad: "ontvangen in maart" gaat over de betaaldatum, "openstaand"
        // over de vervaldatum. Dezelfde kolom als waarop gefilterd wordt, zodat
        // de kopjes niet iets anders zeggen dan het totaal.
        $kolom = $this->filter->datumkolom($filters['tab']);

        $payments = (clone $query)
            ->orderByDesc($kolom)
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
                // Alleen de stappen die de school met de hand mag zetten.
                'next_statuses' => collect($this->handmatig($payment))
                    ->mapWithKeys(fn (PaymentStatus $status) => [$status->value => $status === PaymentStatus::Open && $payment->status === PaymentStatus::Paid ? 'Openstaand (vergissing herstellen)' : $status->label()]),
                'method' => $payment->method?->label(),
                'method_value' => $payment->method?->value,
                'description' => $payment->description,
                'due_on' => $payment->due_on->format('d-m-Y'),
                'paid_at' => $payment->paid_at?->format('d-m-Y'),
                'is_overdue' => $payment->isOverdue(),
                ...$this->groep($payment, $kolom),
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
            // Het aanbod om op te filteren: alles waar ooit iets voor is
            // afgenomen, plus wat nu te koop staat.
            'products' => Product::orderBy('name')->get(['id', 'name'])
                ->map(fn (Product $product) => ['id' => $product->id, 'name' => $product->name]),
            // Dezelfde periode als de lijst: kies je "vorige maand", dan gaat
            // ook het cijfer Ontvangen daarover.
            'summary' => $this->overview->summary($filters['period']),
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

        if ($nieuw !== $payment->status && ! in_array($nieuw, $this->handmatig($payment), true)) {
            return back()->withErrors(['status' => "Een rekening die op '{$payment->status->label()}' staat kan niet met de hand naar '{$nieuw->label()}'."]);
        }

        if ($nieuw === $payment->status) {
            $payment->update(['method' => $methode ?? $payment->method]);
        } elseif ($payment->status === PaymentStatus::Paid && $nieuw === PaymentStatus::Open) {
            // Een vergissing herstellen: alleen bij een rekening die met de hand
            // op betaald stond (zie handmatig()), dus buiten de machine om.
            $payment->forceFill(['status' => $nieuw, 'paid_at' => null])->save();
        } else {
            $payment->transitionTo($nieuw, [
                'paid_at' => $nieuw === PaymentStatus::Paid ? ($payment->paid_at ?? now()) : ($nieuw->countsAsRevenue() || $nieuw === PaymentStatus::Refunded ? $payment->paid_at : null),
                'method' => $methode ?? $payment->method,
            ]);
        }

        // Hoort de betaling bij een inschrijving, dan volgt die de stand:
        // betaald bevestigt, mislukt zet hem op "betaling mislukt".
        app(SettleOrder::class)->handle($payment->refresh());

        $melding = $methode !== null && $nieuw === PaymentStatus::Paid
            ? "De betaling staat op betaald ({$methode->label()})."
            : "De betaling staat nu op '{$nieuw->label()}'.";

        return back()->with('status', $melding);
    }

    /**
     * Onder welk kopje deze rekening hoort.
     *
     * De datum staat zo één keer boven een groepje in plaats van op elke regel;
     * bij een school die op de eerste van de maand int scheelt dat dertig keer
     * dezelfde datum lezen.
     *
     * @return array{group_key: string, group_label: string}
     */
    protected function groep(Payment $payment, string $kolom): array
    {
        $datum = $payment->{$kolom} ?? $payment->due_on;

        return [
            'group_key' => $datum->format('Y-m-d'),
            'group_label' => ($kolom === 'paid_at' ? 'Betaald op ' : 'Vervalt ').$datum->translatedFormat('j F Y'),
        ];
    }

    /**
     * Waar de school deze rekening met de hand naartoe mag zetten.
     *
     * De stappen uit de statusmachine, plus één uitzondering: wie een
     * rekening per ongeluk op betaald zette, mag dat terugdraaien - zolang de
     * betaling niet via de betaalprovider liep.
     *
     * @return list<PaymentStatus>
     */
    protected function handmatig(Payment $payment): array
    {
        $stappen = $payment->status->manualNext();

        if ($payment->status === PaymentStatus::Paid && $payment->external_reference === null) {
            array_unshift($stappen, PaymentStatus::Open);
        }

        return $stappen;
    }
}
