<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Een uitnodiging vanaf de wachtlijst: er is plek, met een betaallink en een
 * tijdslimiet. Verloopt hij, dan schuift de volgende door (onderdeel 6).
 */
class WaitlistInvitation extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'enrollment_id',
        'token',
        'sent_at',
        'expires_at',
        'accepted_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $uitnodiging) {
            $uitnodiging->token ??= Str::random(48);
        });
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expired_at === null && $this->expires_at->isFuture();
    }
}
