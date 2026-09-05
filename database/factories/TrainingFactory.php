<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Training>
 */
class TrainingFactory extends Factory
{
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-2 weeks', '+4 weeks'))->setTime(18, 0);

        return [
            'school_id' => School::factory(),
            'group_id' => Group::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
            'location' => 'Sportpark De Vliert',
            'note' => null,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(function () {
            $start = now()->addWeek()->setTime(18, 0);

            return ['starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(90)];
        });
    }

    public function past(): static
    {
        return $this->state(function () {
            $start = now()->subWeek()->setTime(18, 0);

            return ['starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(90)];
        });
    }
}
