<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een plek waar een school traint.
 *
 * Bewust een eigen ding en geen tekstveld: een school met twee locaties wil ze
 * naast elkaar zien, en drie schrijfwijzen van hetzelfde sportpark maken elk
 * overzicht onbruikbaar.
 *
 * Wat er is afgesproken houdt de naam van toen vast (`trainings.location` en
 * de kolommen daarnaast). Een locatie hernoemen mag de agenda van vorig seizoen
 * niet herschrijven.
 */
class Location extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'name',
        'address',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
