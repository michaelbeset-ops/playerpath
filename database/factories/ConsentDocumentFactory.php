<?php

namespace Database\Factories;

use App\Models\ConsentDocument;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConsentDocument> */
class ConsentDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'key' => 'avg',
            'title' => 'Privacy (AVG)',
            'body' => 'De tekst.',
            'version' => 1,
            'required' => true,
        ];
    }
}
