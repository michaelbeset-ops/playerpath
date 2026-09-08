<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De tenant. Dit model heeft zelf géén school-scope: het ís de school.
 * Wie welke school mag zien regelt SchoolPolicy.
 */
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'logo_path',
        'brand_color',
        'contact_name',
        'contact_email',
        'contact_phone',
        'notes',
        'features',
        'package',
        'birthday_greeting',
        'birthday_message',
        'rating_settings',
        'enrollment_settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'features' => 'array',
            'birthday_greeting' => 'boolean',
            'rating_settings' => 'array',
            'enrollment_settings' => 'array',
            // Niet in $fillable: de onboarding-stand gaat alleen via
            // OnboardingState, want daar staat wat de sleutels betekenen.
            'onboarding' => 'array',
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
