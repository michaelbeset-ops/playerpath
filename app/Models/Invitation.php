<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Een uitnodiging om mee te doen met deze school.
 *
 * Uitnodigen ging via de wachtwoord-vergeten-route, en dat kan twee dingen
 * niet: onthouden welk kind bij een ouder hoort, en een eigen geldigheidsduur
 * dragen. Allebei nodig zodra je in bulk uitnodigt en later wilt zien wie er
 * nog niet is ingelogd.
 *
 * Drie eigenschappen die dit veilig houden:
 *
 * 1. **Het token is 64 willekeurige tekens** en staat in de URL. Niet te raden,
 *    en het geeft alleen recht om één account te activeren.
 * 2. **De uitnodiging verloopt**, met een termijn die de school zelf zet. Een
 *    link uit een oude mailbox hoort na een tijd niets meer te doen.
 * 3. **Een gebruikte uitnodiging is op.** `accepted_at` wordt gezet en het
 *    token werkt daarna niet meer; opnieuw versturen maakt een nieuw token.
 */
class Invitation extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'name',
        'email',
        'role',
        'player_ids',
        'relationship',
        'invited_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'player_ids' => 'array',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function nieuwToken(): string
    {
        return Str::random(64);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Kan er nog iets mee? */
    public function isOpen(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /** In woorden, voor het scherm. */
    public function status(): string
    {
        return match (true) {
            $this->isAccepted() => 'geaccepteerd',
            $this->isExpired() => 'verlopen',
            default => 'open',
        };
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    /** De kinderen die bij activatie aan deze ouder gekoppeld worden. */
    public function players(): Collection
    {
        $ids = $this->player_ids ?? [];

        return $ids === []
            ? Player::query()->whereRaw('1 = 0')->get()
            : Player::query()->whereIn('id', $ids)->get();
    }
}
