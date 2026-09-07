<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een gegeven toestemming: wie, voor welk kind, welk document, welke versie,
 * wanneer. De versie is de reden dat dit bestaat: een andere tekst is een
 * nieuwe versie, en die heeft deze ouder niet getekend.
 */
class Consent extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'user_id',
        'player_id',
        'consent_document_id',
        'version',
        'accepted_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ConsentDocument::class, 'consent_document_id');
    }

    /** Geldt deze toestemming nog voor de huidige tekst? */
    public function isCurrent(): bool
    {
        return $this->document !== null && $this->version === $this->document->version;
    }
}
