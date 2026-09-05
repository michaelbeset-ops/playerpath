<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\SyncPayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Waar Mollie meldt dat er iets met een betaling is gebeurd.
 *
 * Vier eigenschappen die je niet moet weghalen:
 *
 * 1. **We geloven de melding niet.** Mollie stuurt alleen een id; wat die
 *    betaling waard is halen we zelf op. Iedereen kan hier een id posten.
 * 2. **Altijd 200 teruggeven**, ook bij een onbekend id. Een foutcode laat
 *    Mollie eindeloos opnieuw proberen, en zegt bovendien aan de buitenwereld
 *    of een id bij ons bestaat.
 * 3. **Geen sessie, geen CSRF, geen ingelogde gebruiker.** Dit is server-naar-
 *    server. De school komt daarom uit de betaling zelf, niet uit een account.
 * 4. **Buiten de school-scope zoeken.** Er is hier geen actieve school, dus de
 *    global scope zou fail-closed niets vinden. Daarna zetten we de school
 *    expliciet, zodat alles wat volgt weer binnen de juiste tenant draait.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected SyncPayment $sync,
        protected Tenancy $tenancy,
    ) {}

    public function __invoke(Request $request): Response
    {
        $reference = (string) $request->input('id');

        if ($reference === '') {
            return response('', 200);
        }

        try {
            $payment = Payment::withoutSchoolScope()
                ->where('external_reference', $reference)
                ->first();

            if ($payment === null) {
                return response('', 200);
            }

            $this->tenancy->forSchool($payment->school, function () use ($payment, $reference) {
                $this->sync->handle($payment, $this->gateway->fetch($reference));
            });
        } catch (Throwable $e) {
            // Niet doorgooien: dat zou Mollie laten herhalen op iets wat bij ons
            // stuk is, niet bij hen. Wel loggen, want een stille fout hier
            // betekent een betaling die nooit doorkomt.
            Log::error('Webhook van Mollie mislukt', ['reference' => $reference, 'exception' => $e]);
        }

        return response('', 200);
    }
}
