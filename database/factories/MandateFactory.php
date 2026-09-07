<?php

namespace Database\Factories;

use App\Enums\MandateStatus;
use App\Models\Mandate;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mandate> */
class MandateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'provider' => 'mollie',
            'customer_reference' => 'cst_'.fake()->unique()->lexify('??????????'),
            'mandate_reference' => 'mdt_'.fake()->unique()->lexify('??????????'),
            'method' => 'directdebit',
            'status' => MandateStatus::Valid,
            'valid_from' => now(),
        ];
    }
}
