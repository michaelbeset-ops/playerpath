<?php

namespace App\Models;

use App\Enums\Daypart;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Het gewone ritme van een trainer: "dinsdagavond kan ik".
 *
 * Een rij betekent beschikbaar. Wat er niet staat is dat niet — zo is invullen
 * aanvinken en niets anders. De uitzonderingen erop staan in
 * `AvailabilityException`.
 */
class AvailabilityRule extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'user_id',
        'weekday',
        'daypart',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'daypart' => Daypart::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
