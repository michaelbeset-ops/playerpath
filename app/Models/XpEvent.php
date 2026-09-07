<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eén XP-boeking.
 *
 * De boekhouding achter het level. `players.xp` is de som; dit is de waarheid.
 * Elke regel zegt waarvoor de punten waren en waar ze aan hangen, zodat een
 * afgevinkte aanwezigheid die wordt teruggedraaid ook zijn XP weer kwijtraakt.
 */
class XpEvent extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'player_id',
        'source',
        'points',
        'description',
        'reference_type',
        'reference_id',
        'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
