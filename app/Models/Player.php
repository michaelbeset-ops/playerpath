<?php

namespace App\Models;

use App\Enums\PlayerPosition;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'position' => PlayerPosition::class,
            'is_active' => 'boolean',
        ];
    }

    /** Het eigen inlogaccount van de speler (mag ontbreken). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** De ouders/verzorgers van deze speler. */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_player')
            ->withPivotValue('school_id', $this->school_id)
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withPivotValue('school_id', $this->school_id)
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
