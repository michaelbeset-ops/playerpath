<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Tarief '.fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'amount_cents' => fake()->numberBetween(1500, 6000),
            'interval' => BillingInterval::Monthly,
            'is_active' => true,
        ];
    }
}
