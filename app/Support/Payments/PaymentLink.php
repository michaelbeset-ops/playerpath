<?php

namespace App\Support\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\URL;

/**
 * Een betaallink die werkt zonder in te loggen.
 *
 * Een net ingeschreven ouder heeft nog geen wachtwoord: die kiest er een via de
 * mail die daarover gaat. Zou de betaallink een inlog vereisen, dan moet hij
 * eerst dat hele rondje doen voordat hij kan afrekenen — en precies daar haakt
 * iemand af.
 *
 * Drie dingen houden dit veilig, en die moet je niet weghalen:
 *
 * 1. **De link is ondertekend en verloopt.** Sleutelen aan het id of het bedrag
 *    maakt de handtekening ongeldig; na veertien dagen doet hij niets meer.
 * 2. **Je kunt er alleen mee betalen.** De pagina toont het bedrag, de
 *    omschrijving en de school — geen geboortedatum, geen rapporten, geen
 *    andere betalingen.
 * 3. **De uitkomst komt van de provider**, via de webhook, net als bij een
 *    ingelogde ouder. Wat de browser terugmeldt is hooguit een hint.
 */
class PaymentLink
{
    /** Hoe lang een betaallink bruikbaar blijft. */
    public const DAGEN_GELDIG = 14;

    public function for(Payment $payment): string
    {
        return URL::temporarySignedRoute(
            'public-pay.show',
            now()->addDays(self::DAGEN_GELDIG),
            ['payment' => $payment->id],
        );
    }
}
