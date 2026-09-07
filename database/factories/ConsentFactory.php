<?php

namespace Database\Factories;

use App\Models\Consent;
use App\Models\ConsentDocument;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Consent> */
class ConsentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'consent_document_id' => ConsentDocument::factory(),
            'version' => 1,
            'accepted_at' => now(),
        ];
    }
}
