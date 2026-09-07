<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De kaart zoals hij was aan het eind van een jaargang.
 *
 * Gaat een speler een categorie omhoog, dan ligt de lat hoger en gaat zijn
 * kaart opnieuw beginnen te groeien. De oude kaart verdwijnt niet: hij blijft
 * hier staan als "Seizoen 2025/26 · O12", zodat een kind terug kan kijken naar
 * waar hij vandaan kwam.
 */
class PlayerCardSeason extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'player_id',
        'season',
        'age_category',
        'overall_rating',
        'category_ratings',
        'xp',
        'level',
        'report_count',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'category_ratings' => 'array',
            'xp' => 'integer',
            'report_count' => 'integer',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
