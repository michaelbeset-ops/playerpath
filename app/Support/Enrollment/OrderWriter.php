<?php

namespace App\Support\Enrollment;

use App\Enums\OrderStatus;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentOption;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Van een berekening (OrderBuilder) een order met regels maken en de
 * inschrijvingen eraan hangen. Eén plek, gebruikt bij het indienen én bij het
 * doorschuiven vanaf de wachtlijst, zodat een order er altijd hetzelfde
 * uitziet, waar hij ook vandaan komt.
 *
 * Hier telt ook een gebruikte kortingscode mee (`discounts.uses`), met de
 * korting op slot: twee ouders die tegelijk de laatste keer van een code
 * gebruiken mogen hem niet allebei krijgen.
 */
class OrderWriter
{
    public function __construct(protected OrderBuilder $builder) {}

    /**
     * @param  list<array{product: Product, option: PaymentOption, child_name: string, player_id: int|null, enrollment: Enrollment}>  $regels
     */
    public function write(EnrollmentSettings $settings, array $regels, User $ouder, ?string $code = null, ?string $note = null): Order
    {
        return DB::transaction(fn () => $this->schrijf($settings, $regels, $ouder, $code, $note));
    }

    /** @param  list<array{product: Product, option: PaymentOption, child_name: string, player_id: int|null, enrollment: Enrollment}>  $regels */
    protected function schrijf(EnrollmentSettings $settings, array $regels, User $ouder, ?string $code, ?string $note): Order
    {
        $berekening = $this->builder->build($settings, $regels, $ouder, $code);

        foreach (array_unique(array_filter(array_column($berekening['lines'], 'discount_id'))) as $kortingId) {
            $korting = Discount::query()->whereKey($kortingId)->lockForUpdate()->first();

            if ($korting === null || ! $korting->isUsable()) {
                throw ValidationException::withMessages([
                    'code' => 'Deze kortingscode is net niet meer geldig. Haal hem weg en probeer het opnieuw.',
                ]);
            }

            $korting->increment('uses');
        }

        $order = Order::create([
            'user_id' => $ouder->id,
            'status' => OrderStatus::Concept,
            'discount_code' => ($berekening['code']['valid'] ?? false) ? $berekening['code']['code'] : null,
            'note' => $note,
        ]);

        $perSpeler = collect($regels)->keyBy(fn ($r) => (string) $r['player_id']);

        foreach ($berekening['lines'] as $regel) {
            $order->lines()->create([
                'type' => $regel['type'],
                'description' => $regel['description'],
                'amount_cents' => $regel['amount_cents'],
                'vat_rate' => $regel['vat_rate'],
                'product_id' => $regel['product_id'],
                'player_id' => $regel['player_id'],
                'discount_id' => $regel['discount_id'],
                'enrollment_id' => $regel['player_id'] !== null ? ($perSpeler->get((string) $regel['player_id'])['enrollment']->id ?? null) : null,
            ]);
        }

        $order->recalculate();

        foreach ($regels as $regel) {
            $regel['enrollment']->forceFill(['order_id' => $order->id])->save();
        }

        return $order;
    }
}
