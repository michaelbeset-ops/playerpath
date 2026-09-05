<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlayerPosition;
use App\Models\Enrollment;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-15 years', '-7 years'),
            'position' => PlayerPosition::Keeper,
            'guardian_name' => fake()->name(),
            'guardian_email' => fake()->unique()->safeEmail(),
            'guardian_phone' => fake()->phoneNumber(),
            'relationship' => 'moeder',
            'plan_id' => null,
            'payment_method' => PaymentMethod::DirectDebit,
            'note' => null,
            'status' => EnrollmentStatus::Pending,
        ];
    }
}
