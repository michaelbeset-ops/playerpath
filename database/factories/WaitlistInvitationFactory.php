<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\School;
use App\Models\WaitlistInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WaitlistInvitation> */
class WaitlistInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'enrollment_id' => Enrollment::factory(),
            'sent_at' => now(),
            'expires_at' => now()->addDays(3),
        ];
    }
}
