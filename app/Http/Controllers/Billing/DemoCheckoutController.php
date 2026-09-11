<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\SyncPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Money\Money;
use App\Support\Payments\DemoGateway;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\RemotePayment;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het nagebootste betaalscherm van de demo-provider.
 *
 * Dit is wat er in het echt bij iDEAL gebeurt: bank kiezen, betalen of
 * annuleren, terug naar de app. De uitkomst gaat door dezelfde deur als een
 * echte webhook (`SyncPayment`), dus order, inschrijving, mandaat en mails
 * volgen vanzelf. Alleen bereikbaar met de ondertekende link die de
 * DemoGateway uitdeelt, en alleen zolang de demo aanstaat.
 */
class DemoCheckoutController extends Controller
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected SyncPayment $sync,
        protected Tenancy $tenancy,
    ) {}

    public function show(Request $request, int $payment): Response
    {
        $betaling = $this->betaling($payment);

        return Inertia::render('billing/DemoPay', [
            'payment' => [
                'description' => $betaling->description,
                'amount' => Money::format($betaling->amount_cents),
                'player' => $betaling->player?->first_name,
                'paid' => $betaling->status === PaymentStatus::Paid,
            ],
            'school' => ['name' => $betaling->school->name],
            'mandate' => (bool) $request->query('mandaat'),
            'banks' => ['ABN AMRO', 'ASN Bank', 'bunq', 'ING', 'Knab', 'Rabobank', 'RegioBank', 'SNS', 'Triodos Bank'],
            'completeUrl' => URL::temporarySignedRoute('demo-pay.complete', now()->addHour(), [
                'payment' => $betaling->id,
                'terug' => $request->query('terug'),
                'mandaat' => $request->query('mandaat', 0),
            ]),
        ]);
    }

    public function complete(Request $request, int $payment): RedirectResponse
    {
        $betaling = $this->betaling($payment);

        $data = $request->validate([
            'result' => ['required', 'in:paid,cancelled,failed'],
            'bank' => ['nullable', 'string', 'max:40'],
        ]);

        $mandaat = (bool) $request->query('mandaat');
        $klant = $mandaat ? $this->gateway->ensureCustomerFor($betaling->order?->user ?? $betaling->player?->guardians()->first() ?? $request->user()) : null;

        $uitkomst = new RemotePayment(
            reference: $betaling->external_reference ?? 'demo_'.$betaling->id,
            status: match ($data['result']) {
                'paid' => PaymentStatus::Paid,
                'failed' => PaymentStatus::Failed,
                default => PaymentStatus::Cancelled,
            },
            paidAt: $data['result'] === 'paid' ? CarbonImmutable::now() : null,
            method: PaymentMethod::Ideal,
            customerReference: $klant,
            // Een geslaagde eerste betaling met klant legt het mandaat vast,
            // net als bij Mollie.
            mandateReference: $mandaat && $data['result'] === 'paid' ? 'mdt_demo_'.$klant : null,
        );

        // De verwerking loopt in de school van de betaling: hier is niemand
        // ingelogd (een link uit een mail), dus de scope staat dicht.
        $this->tenancy->forSchool($betaling->school, fn () => $this->sync->handle($betaling, $uitkomst));

        $terug = (string) $request->query('terug');

        return redirect()->to($terug !== '' ? $terug : route('dashboard'));
    }

    /**
     * De betaling achter de link. Zonder demo bestaat dit scherm niet: 404.
     */
    protected function betaling(int $id): Payment
    {
        abort_unless($this->gateway instanceof DemoGateway, 404);

        return Payment::withoutSchoolScope()->with(['school', 'player'])->findOrFail($id);
    }
}
