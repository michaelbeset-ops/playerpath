<?php

namespace App\Support\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * Wat de betaalprovider over één betaling zegt.
 *
 * Bewust een eigen waardeobject en niet het resource-object van Mollie: zo
 * kent de rest van de app alleen onze eigen begrippen, en is een andere
 * provider later een tweede vertaling in plaats van een verbouwing.
 */
final readonly class RemotePayment
{
    public function __construct(
        /** Het kenmerk bij de provider, bijvoorbeeld tr_7UhSN1zuXS. */
        public string $reference,
        public PaymentStatus $status,
        public ?CarbonImmutable $paidAt = null,
        public ?PaymentMethod $method = null,
        /** Alleen gevuld direct na het starten van een betaling. */
        public ?string $checkoutUrl = null,
        // Wat de provider over de betaler teruggeeft: nooit een IBAN, alleen kenmerken.
        public ?string $customerReference = null,
        public ?string $mandateReference = null,
    ) {}
}
