<?php

namespace Tests\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\RemotePayment;
use Carbon\CarbonImmutable;

/**
 * Een betaalprovider die niet met het internet praat.
 *
 * De app kent alleen PaymentGateway, dus dit is genoeg om de hele keten te
 * testen: knop, webhook, herinneringen. De vertaling naar Mollie zelf staat
 * apart in MollieGatewayTest.
 */
class FakeGateway implements PaymentGateway
{
    public bool $connected = true;

    /** @var array<string, RemotePayment> */
    public array $remote = [];

    /** @var list<array{payment: int, returnUrl: string, webhookUrl: string}> */
    public array $started = [];

    public ?\Throwable $failWith = null;

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function name(): string
    {
        return 'TestProvider';
    }

    public function statusMessage(): string
    {
        return 'Testprovider actief.';
    }

    public function start(Payment $payment, string $returnUrl, string $webhookUrl): RemotePayment
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->started[] = ['payment' => $payment->id, 'returnUrl' => $returnUrl, 'webhookUrl' => $webhookUrl];

        $remote = new RemotePayment(
            reference: 'tr_test_'.$payment->id,
            status: PaymentStatus::Open,
            checkoutUrl: 'https://betaalprovider.test/checkout/'.$payment->id,
        );

        return $this->remote[$remote->reference] = $remote;
    }

    public function fetch(string $reference): RemotePayment
    {
        return $this->remote[$reference] ?? new RemotePayment($reference, PaymentStatus::Open);
    }

    /** Doen alsof de betaling bij de provider is voldaan. */
    public function markPaid(string $reference, PaymentMethod $method = PaymentMethod::Ideal): void
    {
        $this->remote[$reference] = new RemotePayment(
            reference: $reference,
            status: PaymentStatus::Paid,
            paidAt: CarbonImmutable::now(),
            method: $method,
        );
    }

    public function markStatus(string $reference, PaymentStatus $status): void
    {
        $this->remote[$reference] = new RemotePayment($reference, $status);
    }
}
