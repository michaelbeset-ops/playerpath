<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'player_id' => Player::factory(),
            'subscription_id' => null,
            'amount_cents' => 2750,
            'status' => PaymentStatus::Open,
            'method' => PaymentMethod::DirectDebit,
            'description' => 'Contributie',
            'due_on' => now()->toDateString(),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed, 'paid_at' => null]);
    }
}
