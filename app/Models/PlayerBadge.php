<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een eigen mijlpaal die aan een speler is toegekend.
 *
 * Alleen voor mijlpalen die de school zelf heeft bedacht (zie
 * BadgeSettings::customBadges()). De standaardmijlpalen worden afgeleid en
 * staan hier bewust niet in: dan zouden er twee bronnen zijn die uit elkaar
 * kunnen lopen.
 */
class PlayerBadge extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'player_id',
        'badge_key',
        'awarded_by',
        'awarded_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'awarded_on' => 'date',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
