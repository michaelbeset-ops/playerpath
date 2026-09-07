<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\TrainingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    /** @use HasFactory<TrainingFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'group_id',
        'slot_id',
        'starts_at',
        'ends_at',
        'location',
        'location_id',
        'note',
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
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * De trainer(s) die bij deze training staan.
     *
     * Informatief: het bepaalt niet wie er bij mag. Elke trainer ziet het hele
     * rooster en kan overal afvinken, zodat invallen en ruilen niet vastloopt.
     */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivotValue('school_id', $this->pivotSchoolId())
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Het geboekte moment, bij een privétraining. */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /** Een privétraining hoort bij één kind en niet bij een groep. */
    public function isPrivate(): bool
    {
        return $this->group_id === null;
    }

    /**
     * Wie worden hier verwacht: de actieve spelers van de groep.
     *
     * Bewust niet vastgelegd bij het inplannen. Komt er morgen een speler bij
     * de groep, dan staat hij vanzelf op de lijst van de training van overmorgen.
     *
     * Bij een privétraining is er geen groep: dan is het het kind dat geboekt
     * heeft, en dat is er precies één.
     */
    public function expectedPlayers(): Collection
    {
        if ($this->group === null) {
            $speler = $this->slot?->player;

            return $speler === null ? new Collection : new Collection([$speler]);
        }

        return $this->group->players()->active()->orderBy('first_name')->get();
    }

    /** Waar deze training over gaat, in het rooster. */
    public function label(): string
    {
        return $this->group?->name
            ?? ($this->slot?->product?->name ?? 'Privétraining');
    }

    /**
     * De trainingen die van deze trainer zijn.
     *
     * Een training zonder gekoppelde trainers telt als "van iedereen": koppelen
     * is informatief en veel scholen doen het niet. Zou dit strikt filteren,
     * dan is "Mijn trainingen" bij die scholen altijd leeg. Deze regel staat
     * hier op één plek, zodat de kalender en Mijn trainingen niet uit elkaar
     * kunnen lopen.
     */
    public function scopeForTrainer(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('trainers')
            ->orWhereHas('trainers', fn (Builder $t) => $t->whereKey($user->id)));
    }

    /** Is deze training van die trainer? Zelfde regel als scopeForTrainer(). */
    public function belongsToTrainer(User $user): bool
    {
        return $this->trainers->isEmpty() || $this->trainers->contains('id', $user->id);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at');
    }

    public function hasPassed(): bool
    {
        return $this->starts_at->isPast();
    }
}
