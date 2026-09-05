<?php

namespace App\Support\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;

/**
 * Mollie achter de PaymentGateway-naad.
 *
 * Twee dingen die hier makkelijk misgaan en daarom apart getest zijn:
 *
 * 1. **Het bedrag.** Mollie wil een string met twee decimalen ("12.50"), wij
 *    bewaren centen. Die omzetting gaat via intdiv en het rekenkundige restant,
 *    nooit via een float — 1250 / 100 is in floating point niet exact 12,50.
 * 2. **De status.** Mollie kent meer toestanden dan wij. Alles wat geen geld
 *    heeft opgeleverd en niet meer gaat opleveren (mislukt, verlopen,
 *    geannuleerd) valt bij ons onder "mislukt", zodat de eigenaar het terugziet
 *    in het overzicht in plaats van dat het stilletjes op openstaand blijft staan.
 */
class MollieGateway implements PaymentGateway
{
    public function __construct(private readonly MollieApiClient $client) {}

    public function isConnected(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'Mollie';
    }

    public function statusMessage(): string
    {
        return 'Betalingen lopen via Mollie.';
    }

    public function start(Payment $payment, string $returnUrl, string $webhookUrl): RemotePayment
    {
        $mollie = $this->client->payments->create([
            'amount' => [
                'currency' => 'EUR',
                'value' => self::toAmount($payment->amount_cents),
            ],
            'description' => $payment->description,
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            // Onze eigen id meesturen, zodat een betaling ook terug te vinden
            // is als het opslaan van het kenmerk hier onverhoopt misgaat.
            'metadata' => [
                'payment_id' => $payment->id,
                'school_id' => $payment->school_id,
            ],
        ]);

        return $this->toRemote($mollie);
    }

    public function fetch(string $reference): RemotePayment
    {
        return $this->toRemote($this->client->payments->get($reference));
    }

    private function toRemote(MolliePayment $mollie): RemotePayment
    {
        return new RemotePayment(
            reference: $mollie->id,
            status: self::toStatus($mollie),
            paidAt: $mollie->paidAt ? CarbonImmutable::parse($mollie->paidAt) : null,
            method: self::toMethod($mollie->method),
            checkoutUrl: $mollie->getCheckoutUrl(),
        );
    }

    /** 1250 => "12.50" — via centen, nooit via een float. */
    public static function toAmount(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), abs($cents % 100));
    }

    public static function toStatus(MolliePayment $mollie): PaymentStatus
    {
        $bedrag = self::cents($mollie->amount ?? null);
        $gestorneerd = self::cents($mollie->amountChargedBack ?? null);
        $terugbetaald = self::cents($mollie->amountRefunded ?? null);

        // Een stornering wint van alles: het geld is binnengeweest en weer weg,
        // en daar moet de eigenaar iets mee. Ook een gedeeltelijke.
        if ($gestorneerd > 0) {
            return PaymentStatus::ChargedBack;
        }

        // Alleen een volledige terugbetaling telt als terugbetaald. Is er maar
        // een deel terug, dan is de rekening in de kern nog gewoon voldaan;
        // hem op "terugbetaald" zetten zou de omzet laten verdampen.
        if ($terugbetaald > 0 && $terugbetaald >= $bedrag) {
            return PaymentStatus::Refunded;
        }

        if ($mollie->isPaid()) {
            return PaymentStatus::Paid;
        }

        if ($mollie->isFailed() || $mollie->isExpired() || $mollie->isCanceled()) {
            return PaymentStatus::Failed;
        }

        return PaymentStatus::Open;
    }

    /**
     * Een bedrag-object van Mollie ({currency, value}) naar centen.
     *
     * Mollie stuurt "27.50" als string. Via Money::toCents, want dat is in dit
     * project de enige plek waar tekst naar centen gaat.
     */
    private static function cents(mixed $amount): int
    {
        $waarde = is_object($amount) ? ($amount->value ?? null) : null;

        return $waarde === null ? 0 : Money::toCents((string) $waarde);
    }

    public static function toMethod(?string $method): ?PaymentMethod
    {
        return match ($method) {
            'ideal' => PaymentMethod::Ideal,
            'directdebit' => PaymentMethod::DirectDebit,
            'banktransfer' => PaymentMethod::Transfer,
            // Mollie kent meer methodes dan wij aanbieden (creditcard, Bancontact,
            // ...). Die laten we leeg in plaats van ze op een verkeerde noemer te
            // schuiven; het bedrag en de status kloppen dan nog steeds.
            default => null,
        };
    }
}
