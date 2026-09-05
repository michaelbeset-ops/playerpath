<?php

namespace Database\Factories;

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-7 years'),
            'position' => fake()->randomElement(PlayerPosition::cases()),
            'is_active' => true,
        ];
    }

    public function keeper(): static
    {
        return $this->state(fn () => ['position' => PlayerPosition::Keeper]);
    }

    public function veldspeler(): static
    {
        return $this->state(fn () => ['position' => PlayerPosition::Field]);
    }
}
