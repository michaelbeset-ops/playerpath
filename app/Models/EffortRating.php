<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Wat een kind bij één training aan inzetpunten kreeg: de inzet, de houding
 * en een notitie. Alleen voor de inzetkaart; zie Actions\Trainings\RecordEffort.
 *
 * `effort` en `attitude` zijn de sleutel van een trede uit RatingSettings
 * (n1, n2, ...), de punten staan ernaast zoals ze op dat moment golden.
 */
class EffortRating extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'training_id',
        'player_id',
        'effort',
        'attitude',
        'effort_points',
        'attitude_points',
        'note',
        'rated_by',
    ];

    protected function casts(): array
    {
        return [
            'effort_points' => 'integer',
            'attitude_points' => 'integer',
            'is_demo' => 'boolean',
        ];
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    /** De inzetpunten van deze training, zonder de punten voor aanwezig. */
    public function points(): int
    {
        return $this->effort_points + $this->attitude_points;
    }

    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    /**
     * Welke spelers bij deze training "gedaan" zijn: afwezig gemeld, of met
     * inzetpunten. Eén plek, voor de invulflow en de herinnering na de training.
     *
     * @return Collection<int, int> player_id als sleutel
     */
    public static function doneFor(Training $training): Collection
    {
        $afwezig = $training->attendances()
            ->where('status', AttendanceStatus::Absent->value)
            ->pluck('player_id');

        $beoordeeld = self::query()->where('training_id', $training->id)->pluck('player_id');

        return $afwezig->concat($beoordeeld)->unique()->flip();
    }
}
