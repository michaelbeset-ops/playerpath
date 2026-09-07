<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\BillingType;
use App\Enums\OfferingStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * De betaalwijze volgt het soort, tenzij een test iets anders zegt.
     *
     * Zonder dit zou elk product uit de factory "per maand" zijn, ook een kamp,
     * en dan weigert de app hem als aankoop — precies het soort ruis waar je in
     * twintig tests achteraan zit.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Product $product) {
            if ($product->getAttribute('billing_type') === null) {
                $product->billing_type = $product->type === ProductType::Doorlopend
                    ? BillingType::Maandelijks
                    : BillingType::Eenmalig;
            }

            if (! $product->billing_type->isRecurring()) {
                $product->interval = null;
            }
        });
    }

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Product '.fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'type' => ProductType::Doorlopend,
            'amount_cents' => fake()->numberBetween(1500, 6000),
            'vat_rate' => 21,
            'credits' => null,
            'validity_months' => null,
            'interval' => BillingInterval::Monthly,
            'status' => OfferingStatus::Open,
            'stops_at_end' => true,
            'is_active' => true,
        ];
    }

    /** Een rittenkaart met beurten die opraken. */
    public function rittenkaart(int $credits = 10, ?int $maanden = 6): static
    {
        return $this->state(fn () => [
            'name' => $credits.'-rittenkaart '.fake()->unique()->numberBetween(1, 9999),
            'type' => ProductType::Rittenkaart,
            'billing_type' => BillingType::Eenmalig,
            'credits' => $credits,
            'validity_months' => $maanden,
            'interval' => null,
        ]);
    }

    public function kamp(): static
    {
        return $this->state(fn () => [
            'name' => 'Zomerkamp '.fake()->unique()->numberBetween(1, 9999),
            'type' => ProductType::Kamp,
            'billing_type' => BillingType::Eenmalig,
            'interval' => null,
        ]);
    }

    /** Een blok van een aantal weken, met plekken en een leeftijdsgrens. */
    public function blok(int $weken = 6, ?int $capaciteit = 12): static
    {
        return $this->state(fn () => [
            'name' => 'Blok van '.$weken.' weken '.fake()->unique()->numberBetween(1, 9999),
            'type' => ProductType::Blok,
            'billing_type' => BillingType::Eenmalig,
            'interval' => null,
            'starts_on' => now()->addWeek()->startOfWeek()->toDateString(),
            'ends_on' => now()->addWeeks($weken + 1)->startOfWeek()->toDateString(),
            'capacity' => $capaciteit,
        ]);
    }
}
