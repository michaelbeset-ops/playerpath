<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De tenant. Dit model heeft zelf géén school-scope: het ís de school.
 * Wie welke school mag zien regelt SchoolPolicy.
 */
class School extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'retention_months',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'retention_months' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }
}
