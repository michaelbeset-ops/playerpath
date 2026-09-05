<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'author_id' => null,
            'group_id' => null,
            'training_id' => null,
            'title' => 'Zomerstop',
            'body' => 'In de laatste week van juli zijn er geen trainingen.',
            'recipients_count' => 0,
        ];
    }
}
