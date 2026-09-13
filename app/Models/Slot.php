<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Een beschikbaar moment voor privétraining.
 *
 * Bestaat vóórdat er iemand geboekt heeft - dat is precies het verschil met een
 * training. Zodra er geboekt wordt ontstaat de training erbij, zodat dit uur in
 * de agenda staat en er aanwezigheid en een rapport bij kunnen.
 */
class Slot extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'starts_at',
        'ends_at',
        'location',
        'location_id',
        'player_id',
        'purchase_id',
        'enrollment_id',
        'booked_at',
    ];

    /**
     * De locatie, als er een gekozen is.
     *
     * Heet bewust `venue()` en niet `location()`: `location` is de tekstkolom
     * met de naam zoals die op dat moment was. Zou de relatie zo heten, dan
     * levert `$training->location` de ene keer een string en de andere keer een
     * model op, afhankelijk van wat er toevallig geladen is.
     *
     * Dat de naam ernaast blijft staan is dezelfde regel als bij een aankoop,
     * die naam en bedrag overneemt: een locatie hernoemen mag de agenda van
     * vorig seizoen niet herschrijven.
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'booked_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** De trainer die dit uur beschikbaar heeft. */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function training(): HasOne
    {
        return $this->hasOne(Training::class);
    }

    /**
     * Bezet: geboekt, of vastgehouden door een inschrijving die nog wacht op
     * goedkeuring. Dat laatste ook, anders boekt de volgende ouder hetzelfde
     * uur terwijl de eerste nog op antwoord wacht.
     */
    public function isTaken(): bool
    {
        return $this->player_id !== null || $this->enrollment_id !== null;
    }

    public function isBookable(): bool
    {
        return ! $this->isTaken() && $this->starts_at->isFuture();
    }

    /** Alleen momenten die nog te boeken zijn, eerstvolgende eerst. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->whereNull('player_id')
            ->whereNull('enrollment_id')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at');
    }
}
