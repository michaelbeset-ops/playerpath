<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Product '.fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'type' => ProductType::Abonnement,
            'amount_cents' => fake()->numberBetween(1500, 6000),
            'vat_rate' => 21,
            'credits' => null,
            'validity_months' => null,
            'interval' => BillingInterval::Monthly,
            'is_active' => true,
        ];
    }

    /** Een rittenkaart met beurten die opraken. */
    public function rittenkaart(int $credits = 10, ?int $maanden = 6): static
    {
        return $this->state(fn () => [
            'name' => $credits.'-rittenkaart '.fake()->unique()->numberBetween(1, 9999),
            'type' => ProductType::Rittenkaart,
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
            'interval' => null,
        ]);
    }
}
