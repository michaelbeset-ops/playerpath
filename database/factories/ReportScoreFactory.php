<?php

namespace Database\Factories;

use App\Enums\ReportCategory;
use App\Models\Report;
use App\Models\ReportScore;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportScore>
 */
class ReportScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'report_id' => Report::factory(),
            'category' => fake()->randomElement(ReportCategory::cases()),
            'score' => fake()->numberBetween(4, 9),
        ];
    }
}
