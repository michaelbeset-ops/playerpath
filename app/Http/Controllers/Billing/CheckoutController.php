<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\SyncPayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * De ouder die op "Nu betalen" drukt.
 *
 * De keten is met opzet kort: wij maken een betaling bij de provider aan,
 * sturen de ouder daarheen, en wachten daarna op de webhook. Wat de browser
 * bij terugkomst zegt gebruiken we alleen om het scherm te verversen — nooit
 * om iets op betaald te zetten.
 */
class CheckoutController extends Controller
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected SyncPayment $sync,
    ) {}

    public function pay(Request $request, Payment $payment): Response|RedirectResponse
    {
        $this->magBij($request, $payment);

        if (! $this->gateway->isConnected()) {
            return back()->with('status', $this->gateway->statusMessage());
        }

        if ($payment->status === PaymentStatus::Paid) {
            return back()->with('status', 'Deze betaling is al voldaan.');
        }

        // Een lopende betaling hervatten in plaats van een tweede aanmaken:
        // anders staat er straks twee keer hetzelfde bedrag open omdat iemand
        // halverwege iDEAL zijn browser sloot.
        if ($payment->checkout_url !== null && $payment->status === PaymentStatus::Open) {
            return Inertia::location($payment->checkout_url);
        }

        try {
            $remote = $this->gateway->start(
                $payment,
                route('billing.return', $payment),
                route('webhooks.mollie'),
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['payment' => 'Het starten van de betaling is niet gelukt. Probeer het straks nog eens.']);
        }

        $payment->forceFill([
            'external_reference' => $remote->reference,
            'checkout_url' => $remote->checkoutUrl,
        ])->save();

        return Inertia::location($remote->checkoutUrl);
    }

    /**
     * Waar de ouder landt na iDEAL. Dit zegt niets over de uitkomst: we vragen
     * het de provider zelf, zodat het scherm meteen klopt in plaats van te
     * moeten wachten tot de webhook binnen is.
     */
    public function return(Request $request, Payment $payment): RedirectResponse
    {
        $this->magBij($request, $payment);

        if ($payment->external_reference !== null && $this->gateway->isConnected()) {
            try {
                $this->sync->handle($payment, $this->gateway->fetch($payment->external_reference));
            } catch (Throwable $e) {
                // Geen ramp: de webhook doet het werk nog een keer over.
                report($e);
            }
        }

        return redirect()->route('billing.index')->with(
            'status',
            $payment->refresh()->status === PaymentStatus::Paid
                ? 'Bedankt, we hebben je betaling ontvangen.'
                : 'De betaling is nog niet afgerond. Zodra hij binnen is zie je dat hier.',
        );
    }

    /**
     * Alleen betalingen van je eigen kind. visiblePlayerIds() is dezelfde bron
     * als de spelerskaart en de trainingen, dus dit kan niet uit de pas lopen.
     */
    private function magBij(Request $request, Payment $payment): void
    {
        abort_unless(in_array($payment->player_id, $request->user()->visiblePlayerIds(), strict: true), 403);
    }
}
