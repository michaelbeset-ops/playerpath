<?php

namespace App\Models;

use App\Enums\Daypart;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wat er van het gewone ritme afwijkt: een vakantie, één zaterdag, of juist een
 * week waarin een trainer extra kan.
 *
 * Bewust met een begin- en einddatum in plaats van een rij per dag: "niet
 * beschikbaar 12 t/m 19 oktober" is één handeling, geen acht.
 */
class AvailabilityException extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'user_id',
        'starts_on',
        'ends_on',
        'daypart',
        'available',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'daypart' => Daypart::class,
            'available' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Alles wat vandaag of later nog iets betekent. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereDate('ends_on', '>=', now()->toDateString())->orderBy('starts_on');
    }

    /** Geldt deze uitzondering op dit moment? */
    public function coversDay(CarbonInterface $moment): bool
    {
        return $moment->toDateString() >= $this->starts_on->toDateString()
            && $moment->toDateString() <= $this->ends_on->toDateString();
    }

    public function covers(CarbonInterface $moment): bool
    {
        return $this->coversDay($moment)
            // Zonder dagdeel geldt hij de hele dag.
            && ($this->daypart === null || $this->daypart === Daypart::forTime($moment));
    }

    /** "12 t/m 19 oktober" of "zaterdag 4 oktober, avond". */
    public function describe(): string
    {
        $tekst = $this->starts_on->isSameDay($this->ends_on)
            ? $this->starts_on->translatedFormat('l j F')
            : $this->starts_on->translatedFormat('j F').' t/m '.$this->ends_on->translatedFormat('j F');

        return $this->daypart === null
            ? $tekst
            : $tekst.', '.strtolower($this->daypart->label());
    }
}
