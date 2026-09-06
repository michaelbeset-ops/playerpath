<?php

namespace App\Actions\Products;

use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Een product aan een speler toekennen.
 *
 * Alleen voor de eenmalige soorten. Een abonnement heeft zijn eigen weg
 * (Subscription), met termijnen en incasso; die twee door elkaar halen levert
 * dubbele rekeningen op.
 *
 * Twee dingen die je niet moet omdraaien:
 *
 * 1. **Naam en bedrag worden overgenomen, niet opgezocht.** Verhoogt de school
 *    later haar prijs, dan verandert een gedane afspraak niet mee.
 * 2. **Er ontstaat meteen een openstaande rekening**, ook zonder betaalprovider.
 *    Anders weet een school die per overboeking int niet wie er nog moet
 *    betalen. Op `paid` zetten gebeurt nooit vanzelf; zie CLAUDE.md.
 */
class SellProduct
{
    public function handle(Player $player, Product $product, ?Carbon $startOp = null, ?string $notitie = null): Purchase
    {
        if ($product->type === ProductType::Abonnement) {
            throw new \InvalidArgumentException('Een abonnement loopt via Subscription, niet via een aankoop.');
        }

        $start = $startOp ?? now()->startOfDay();

        return DB::transaction(function () use ($player, $product, $start, $notitie) {
            $aankoop = Purchase::create([
                'player_id' => $player->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'type' => $product->type,
                'amount_cents' => $product->amount_cents,
                'vat_rate' => $product->vat_rate,
                'credits_total' => $product->type->needsCredits() ? $product->credits : null,
                'credits_used' => 0,
                'starts_on' => $start->toDateString(),
                // Geen geldigheidsduur ingesteld betekent onbeperkt geldig, niet
                // "verloopt vandaag".
                'expires_on' => $product->validity_months
                    ? $start->copy()->addMonths($product->validity_months)->toDateString()
                    : null,
                'status' => 'active',
                'note' => $notitie,
            ]);

            // Gratis is geen rekening. Een proefles van nul euro hoort niet in
            // het openstaande-overzicht te belanden.
            if ($aankoop->amount_cents > 0) {
                Payment::create([
                    'player_id' => $player->id,
                    'purchase_id' => $aankoop->id,
                    'amount_cents' => $aankoop->amount_cents,
                    'status' => PaymentStatus::Open,
                    'description' => $aankoop->name,
                    'due_on' => $start->toDateString(),
                ]);
            }

            return $aankoop;
        });
    }
}
