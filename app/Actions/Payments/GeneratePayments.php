<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Money\SplitAmount;
use App\Support\Payments\BillingPeriod;
use Carbon\CarbonImmutable;

/**
 * Facturen maken voor de termijn die nu loopt.
 *
 * Zonder deze stap brengt een abonnement nooit iets voort: het is niet meer
 * dan een afspraak. Hier wordt die afspraak een openstaande betaling die een
 * ouder kan voldoen.
 *
 * Twee regels die je niet moet omdraaien:
 *
 * 1. **Nooit twee keer dezelfde termijn.** Een betaling draagt de startdatum
 *    van zijn termijn; bestaat die al, dan gebeurt er niets. De loop mag dus
 *    zo vaak draaien als je wilt, en een dubbele cron kost niemand geld.
 * 2. **Het bedrag komt van het abonnement, niet van het tarief.** Verhoogt de
 *    school haar prijs, dan verandert een lopend abonnement niet mee; dat is
 *    al vastgelegd bij het aanmaken en geldt hier net zo goed.
 */
class GeneratePayments
{
    /**
     * @return list<Payment> de betalingen die nieuw zijn aangemaakt
     */
    public function handle(Subscription $subscription, ?CarbonImmutable $op = null): array
    {
        $op ??= CarbonImmutable::now();

        $periode = BillingPeriod::forSubscription($subscription, $op);

        if ($periode === null) {
            return [];
        }

        $termijnen = max(1, (int) ($subscription->installments ?? 1));
        $bedragen = SplitAmount::into($subscription->amount_cents, $termijnen);

        $bestaand = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('period_start', $periode->start)
            ->pluck('installment_number')
            ->all();

        $nieuw = [];

        foreach ($bedragen as $index => $bedragInCenten) {
            $nummer = $termijnen > 1 ? $index + 1 : null;

            if (in_array($nummer, $bestaand, strict: false)) {
                continue;
            }

            $nieuw[] = Payment::create([
                'player_id' => $subscription->player_id,
                'subscription_id' => $subscription->id,
                'amount_cents' => $bedragInCenten,
                'vat_rate' => $subscription->vat_rate,
                'status' => PaymentStatus::Open,
                'method' => $subscription->payment_method,
                'description' => $this->omschrijving($subscription, $periode, $nummer, $termijnen),
                // Termijnen vervallen maandelijks na elkaar; één betaling
                // vervalt op de eerste dag van de periode zelf.
                'due_on' => $periode->start->addMonths($index)->toDateString(),
                'period_start' => $periode->start->toDateString(),
                'installment_number' => $nummer,
                'installment_total' => $termijnen > 1 ? $termijnen : null,
            ]);
        }

        return $nieuw;
    }

    private function omschrijving(Subscription $subscription, BillingPeriod $periode, ?int $nummer, int $totaal): string
    {
        // De naam van het product, en anders "Training": een voetbalschool int
        // geen contributie, die verkoopt training. Is het product verwijderd,
        // dan is dat het enige dat je nog met zekerheid kunt zeggen.
        $basis = ($subscription->product?->name ?? 'Training').' '.$periode->label();

        return $nummer === null ? $basis : "{$basis} (termijn {$nummer} van {$totaal})";
    }
}
