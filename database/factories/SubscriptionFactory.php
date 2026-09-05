<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Player;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'player_id' => Player::factory(),
            'plan_id' => null,
            'amount_cents' => 2750,
            'interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'payment_method' => PaymentMethod::DirectDebit,
            'starts_on' => now()->subMonths(3)->toDateString(),
            'ends_on' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Cancelled,
            'ends_on' => now()->toDateString(),
        ]);
    }
}
