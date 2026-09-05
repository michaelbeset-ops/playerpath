<?php

namespace Database\Factories;

use App\Enums\GoalStatus;
use App\Enums\ReportCategory;
use App\Models\Goal;
use App\Models\Player;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'player_id' => Player::factory(),
            'set_by_id' => null,
            'category' => ReportCategory::Reflexen,
            'start_rating' => 60,
            'target_rating' => 80,
            'starts_on' => now()->toDateString(),
            'due_on' => now()->addMonths(3)->toDateString(),
            'note' => null,
            'status' => GoalStatus::Active,
        ];
    }
}
