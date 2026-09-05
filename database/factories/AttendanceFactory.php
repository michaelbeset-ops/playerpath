<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'training_id' => Training::factory(),
            'player_id' => Player::factory(),
            'registration' => null,
            'registered_by_id' => null,
            'status' => null,
        ];
    }
}
