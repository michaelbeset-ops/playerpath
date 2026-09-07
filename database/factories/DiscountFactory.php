<?php

namespace Database\Factories;

use App\Enums\DiscountKind;
use App\Models\Discount;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Discount> */
class DiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'kind' => DiscountKind::Code,
            'name' => 'Vriendenkorting',
            'code' => strtoupper(fake()->unique()->lexify('????????')),
            'percent' => 10,
            'is_active' => true,
        ];
    }
}
