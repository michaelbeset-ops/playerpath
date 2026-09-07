<?php

namespace Tests\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\User;
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

    /** @var list<array{payment: int, returnUrl: string, webhookUrl: string, customer: string|null}> */
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

    /** @var list<array{payment: int, customer: string}> */
    public array $charged = [];

    public bool $mandate = false;

    public function start(Payment $payment, string $returnUrl, string $webhookUrl, ?string $customerReference = null): RemotePayment
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->started[] = ['payment' => $payment->id, 'returnUrl' => $returnUrl, 'webhookUrl' => $webhookUrl, 'customer' => $customerReference];

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
    public function markPaid(string $reference, PaymentMethod $method = PaymentMethod::Ideal, ?string $mandate = null, ?string $customer = null): void
    {
        $this->remote[$reference] = new RemotePayment(
            reference: $reference,
            status: PaymentStatus::Paid,
            paidAt: CarbonImmutable::now(),
            method: $method,
            customerReference: $customer,
            mandateReference: $mandate,
        );
    }

    public function markStatus(string $reference, PaymentStatus $status): void
    {
        $this->remote[$reference] = new RemotePayment($reference, $status);
    }

    public function ensureCustomer(Player $player): string
    {
        if ($player->payment_customer_reference === null) {
            $player->forceFill(['payment_customer_reference' => 'cst_test_'.$player->id])->save();
        }

        return $player->payment_customer_reference;
    }

    public function ensureCustomerFor(User $user): string
    {
        return 'cst_user_'.$user->id;
    }

    public function hasValidMandate(string $customerReference): bool
    {
        return $this->mandate;
    }

    public function charge(Payment $payment, string $customerReference, string $webhookUrl): RemotePayment
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->charged[] = ['payment' => $payment->id, 'customer' => $customerReference];

        $remote = new RemotePayment(
            reference: 'tr_incasso_'.$payment->id,
            status: PaymentStatus::Open,
            method: PaymentMethod::DirectDebit,
        );

        return $this->remote[$remote->reference] = $remote;
    }
}
