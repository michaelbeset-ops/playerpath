<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Groep '.fake()->unique()->numberBetween(1, 9999),
            'age_category' => 'Onder '.fake()->randomElement([9, 11, 13, 15, 17]),
            'is_active' => true,
        ];
    }
}
