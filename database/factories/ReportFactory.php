<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'player_id' => Player::factory(),
            'trainer_id' => User::factory(),
            'reported_on' => fake()->dateTimeBetween('-6 months', 'now'),
            'note' => null,
        ];
    }
}
