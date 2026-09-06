<?php

namespace App\Models;

use App\Enums\PlayerPosition;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'position',
        'is_active',
    ];

    /**
     * De doorgerekende kaartcijfers worden nooit met de hand gezet, alleen
     * door CalculatePlayerCard. Daarom staan ze niet in $fillable.
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'position' => PlayerPosition::class,
            'is_active' => 'boolean',
            'category_ratings' => 'array',
            'overall_rating' => 'integer',
            'rated_at' => 'datetime',
            'shared_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * De klok van de bewaartermijn loopt vanaf de dag dat iemand stopt.
     *
     * Bewust hier en niet in een controller: een speler wordt op meer dan een
     * plek op niet-actief gezet, en de datum mag nooit van de plek afhangen.
     * `deactivated_at` staat daarom ook niet in $fillable.
     */
    protected static function booted(): void
    {
        static::saving(function (Player $player) {
            if ($player->isDirty('is_active')) {
                $player->deactivated_at = $player->is_active ? null : now();
            }
        });
    }

    /** Het eigen inlogaccount van de speler (mag ontbreken). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Het lopende abonnement, als dat er is. */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->active()->latest('starts_on')->first();
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** De ouders/verzorgers van deze speler. */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_player')
            ->withPivotValue('school_id', $this->pivotSchoolId())
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withPivotValue('school_id', $this->pivotSchoolId())
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

    /**
     * De profielfoto, of null.
     *
     * `photo_path` staat niet in $fillable: een foto gaat altijd via
     * Support\Media\ProfilePhoto, want daar wordt het oude bestand opgeruimd
     * en de nieuwe vierkant gemaakt.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path === null ? null : Storage::url($this->photo_path);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isShared(): bool
    {
        return $this->share_token !== null;
    }

    /**
     * De naam zoals hij op een publiek gedeelde kaart mag staan.
     *
     * Voornaam plus initiaal: genoeg om je kind te herkennen, te weinig om een
     * vreemde iets te geven waar hij wat mee kan.
     */
    public function getPublicNameAttribute(): string
    {
        return trim($this->first_name.' '.mb_substr($this->last_name, 0, 1).'.');
    }

    /** Heeft deze speler al cijfers op zijn kaart? */
    public function hasRating(): bool
    {
        return $this->overall_rating !== null;
    }
}
