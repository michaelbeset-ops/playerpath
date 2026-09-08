<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Trainers\TrainerScope;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'age_category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    /**
     * Het aanbod waar deze groep bij hoort, als hij daaruit is ontstaan.
     *
     * Een blok of een kamp krijgt automatisch een groep; de trainingen hangen
     * daaronder, en daardoor blijven aanwezigheid en rapporten werken zoals ze
     * altijd al deden. Een groep die een school zelf maakt heeft dit leeg.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class)
            ->withPivotValue('school_id', $this->pivotSchoolId())
            ->withTimestamps();
    }

    /** Is dit een voorbeeldrij, neergezet bij het opstarten van de school? */
    public function scopeDemo(Builder $query): Builder
    {
        return $query->where('is_demo', true);
    }

    /**
     * Alleen wat de school zelf heeft ingevoerd.
     *
     * De startchecklist telt hiermee: anders is je school "af" zonder dat je
     * ooit een echte speler hebt toegevoegd.
     */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    /**
     * Wat deze gebruiker hiervan mag zien.
     *
     * Voor een trainer zijn dat zijn eigen groepen (zie TrainerScope); voor de
     * eigenaar alles. Eén regel in elke lijst, zodat "mijn" overal hetzelfde is.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(TrainerScope::class)->groups($query, $user);
    }
}
