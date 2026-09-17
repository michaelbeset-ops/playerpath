<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\StartCheckout;
use App\Actions\Payments\SyncPayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\School;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentLink;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Afrekenen via een ondertekende link uit een e-mail, zonder in te loggen.
 *
 * Bedoeld voor het gezin dat net is ingeschreven: dat heeft nog geen wachtwoord
 * gekozen. Zie PaymentLink voor waarom dit veilig kan.
 *
 * Er is geen ingelogde gebruiker en dus geen actieve school. De betaling wordt
 * daarom met withoutSchoolScope() opgezocht - de handtekening op de link is
 * hier het slot - en daarna wordt de school expliciet gezet, zodat alles wat
 * erna gebeurt (de aankoop, het abonnement, de webhook) weer binnen die school
 * blijft. Dezelfde aanpak als de webhook.
 */
class PublicCheckoutController extends Controller
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected StartCheckout $checkout,
        protected SyncPayment $sync,
        protected Tenancy $tenancy,
    ) {}

    public function show(Request $request, int $payment): InertiaResponse
    {
        $betaling = $this->betaling($payment);

        return Inertia::render('billing/PublicPay', [
            'payment' => [
                'description' => $betaling->description,
                'amount' => Money::format($betaling->amount_cents),
                'due_on' => $betaling->due_on->format('d-m-Y'),
                'paid' => $betaling->status === PaymentStatus::Paid,
                // Geannuleerd, terugbetaald of al in behandeling: dan valt er hier niets te betalen.
                'closed' => $betaling->status !== PaymentStatus::Paid && ! $betaling->status->isPayable(),
                // De voornaam mag: die staat ook in de mail waar deze link in
                // stond. De achternaam, de leeftijd en de groep niet.
                'player' => $betaling->player?->first_name,
            ],
            'school' => ['name' => $betaling->school->name],
            'connected' => $this->gateway->isConnected(),
            // Betalen gaat naar dezelfde ondertekende URL: de handtekening
            // hangt aan het adres, niet aan de methode.
            'payUrl' => URL::temporarySignedRoute('public-pay.pay', now()->addHour(), ['payment' => $betaling->id]),
        ]);
    }

    public function pay(Request $request, int $payment): Response|RedirectResponse
    {
        $betaling = $this->betaling($payment);

        if (! $betaling->status->isPayable()) {
            return back();
        }

        if (! $this->gateway->isConnected()) {
            return back()->withErrors(['payment' => 'Online betalen kan hier nu niet. Neem contact op met de school.']);
        }

        try {
            $checkout = $this->checkout->handle(
                $betaling,
                URL::temporarySignedRoute('public-pay.return', now()->addDay(), ['payment' => $betaling->id]),
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['payment' => 'Het starten van de betaling is niet gelukt. Probeer het straks nog eens.']);
        }

        return Inertia::location($checkout);
    }

    /**
     * Waar de betaler landt na iDEAL. Dit zegt niets over de uitkomst: we
     * vragen het de provider zelf, zodat het scherm meteen klopt in plaats van
     * te moeten wachten tot de webhook binnen is.
     */
    public function return(Request $request, int $payment): RedirectResponse
    {
        $betaling = $this->betaling($payment);

        if ($betaling->external_reference !== null && $this->gateway->isConnected()) {
            try {
                $this->sync->handle($betaling, $this->gateway->fetch($betaling->external_reference));
            } catch (Throwable $e) {
                // Geen ramp: de webhook doet het werk nog een keer over.
                report($e);
            }
        }

        return redirect()->to(app(PaymentLink::class)->for($betaling))->with(
            'status',
            $betaling->refresh()->status === PaymentStatus::Paid
                ? 'Bedankt, we hebben je betaling ontvangen.'
                : 'De betaling is nog niet afgerond. Zodra hij binnen is, hoor je het van de school.',
        );
    }

    /**
     * De betaling achter deze link, met de school erbij gezet.
     *
     * Een onbekend id geeft een 404 en niet een melding die verraadt of het
     * bestaat; de handtekening maakt raden sowieso zinloos.
     */
    protected function betaling(int $id): Payment
    {
        $betaling = Payment::withoutSchoolScope()->find($id);

        abort_if($betaling === null, 404);

        // Eerst de school zetten, dan pas de relaties laden: de speler heeft
        // zelf ook een global scope, en die staat zonder actieve school dicht.
        $this->tenancy->set(School::findOrFail($betaling->school_id));

        return $betaling->load(['player', 'school']);
    }
}
