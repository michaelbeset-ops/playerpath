<?php

namespace App\Models;

use App\Enums\DiscountKind;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Een korting: gezin, vroegboek, volume of een code die de school uitdeelt.
 *
 * Een procent óf een vast bedrag. Wat er is toegepast staat als regel op de
 * order, met een verwijzing hierheen; de korting zelf later aanpassen
 * verandert een gedane order dus niet.
 */
class Discount extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'kind',
        'name',
        'code',
        'percent',
        'amount_cents',
        'valid_from',
        'valid_until',
        'max_uses',
        'uses',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DiscountKind::class,
            'percent' => 'integer',
            'amount_cents' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Kan deze korting vandaag nog gebruikt worden? */
    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->valid_from !== null && $this->valid_from->isFuture()) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until->lt(today())) {
            return false;
        }

        return $this->max_uses === null || $this->uses < $this->max_uses;
    }

    /** Hoeveel er van een bedrag af gaat, in centen. Nooit meer dan het bedrag zelf. */
    public function applyTo(int $cents): int
    {
        $korting = $this->percent !== null
            ? intdiv($cents * $this->percent, 100)
            : (int) $this->amount_cents;

        return max(0, min($cents, $korting));
    }
}
