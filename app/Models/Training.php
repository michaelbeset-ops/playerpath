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
        'starts_at',
        'ends_at',
        'location',
        'note',
    ];

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

    /**
     * Wie worden hier verwacht: de actieve spelers van de groep.
     *
     * Bewust niet vastgelegd bij het inplannen. Komt er morgen een speler bij
     * de groep, dan staat hij vanzelf op de lijst van de training van overmorgen.
     */
    public function expectedPlayers(): Collection
    {
        return $this->group->players()->active()->orderBy('first_name')->get();
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
