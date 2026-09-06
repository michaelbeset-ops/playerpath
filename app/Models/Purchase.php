<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wat een speler heeft afgenomen: een rittenkaart, een kamp, een losse training.
 *
 * Naam en bedrag zijn **overgenomen** uit het product, niet opgezocht. Een
 * prijsverhoging of een hernoeming raakt een gedane afspraak dus niet.
 */
class Purchase extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'player_id',
        'product_id',
        'name',
        'type',
        'amount_cents',
        'vat_rate',
        'credits_total',
        'credits_used',
        'starts_on',
        'expires_on',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'amount_cents' => 'integer',
            'vat_rate' => 'integer',
            'credits_total' => 'integer',
            'credits_used' => 'integer',
            'starts_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Hoeveel beurten er nog op de kaart staan. Null als het geen kaart is. */
    public function creditsLeft(): ?int
    {
        return $this->credits_total === null ? null : max(0, $this->credits_total - $this->credits_used);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    /**
     * Kan hier nog een beurt af?
     *
     * Drie voorwaarden, en alle drie nodig: de kaart loopt nog, hij is niet
     * verlopen, en er staat nog wat op. Een verlopen kaart met beurten erop is
     * op: dat is precies waar een rittenkaart voor bestaat.
     */
    public function hasCreditLeft(): bool
    {
        return $this->status === 'active'
            && ! $this->isExpired()
            && ($this->creditsLeft() ?? 0) > 0;
    }

    /**
     * De kaarten waar nog een beurt vanaf kan, oudste eerst.
     *
     * Oudste eerst omdat die als eerste verloopt; andersom zou een ouder
     * beurten kwijtraken die hij had kunnen gebruiken.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('credits_total')
            ->whereColumn('credits_used', '<', 'credits_total')
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()->toDateString()))
            ->orderBy('expires_on')
            ->orderBy('starts_on');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
