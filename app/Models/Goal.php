<?php

namespace App\Models;

use App\Enums\GoalStatus;
use App\Enums\ReportCategory;
use App\Models\Concerns\BelongsToSchool;
use BackedEnum;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use BelongsToSchool, HasFactory;

    /**
     * De categorie voor een doel dat de trainer zelf verzint.
     *
     * Bewust géén extra case in ReportCategory: die enum bepaalt waarop een
     * speler beoordeeld wordt, en daar hoort "overig" niet bij. Vandaar dat
     * `category` hier een gewone string is en niet naar de enum gecast wordt.
     */
    public const CUSTOM = 'overig';

    protected $fillable = [
        'player_id',
        'set_by_id',
        'category',
        'custom_label',
        'start_rating',
        'target_rating',
        'starts_on',
        'due_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoalStatus::class,
            'start_rating' => 'integer',
            'target_rating' => 'integer',
            'starts_on' => 'date',
            'due_on' => 'date',
            'achieved_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GoalStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === GoalStatus::Active;
    }

    /** Een eigen doel: zelf ingetypt, zonder cijfer om aan af te meten. */
    public function isCustom(): bool
    {
        return $this->category === self::CUSTOM;
    }

    /**
     * De categorie als tekst, ook als er een enum wordt toegewezen.
     *
     * Er is bewust geen enum-cast op dit veld (zie CUSTOM), maar code die een
     * ReportCategory toewijst hoort gewoon te werken.
     */
    protected function category(): Attribute
    {
        return Attribute::make(
            set: fn ($waarde) => $waarde instanceof BackedEnum ? $waarde->value : $waarde,
        );
    }

    /** De rapportcategorie, of null bij een eigen doel. */
    public function reportCategory(): ?ReportCategory
    {
        return ReportCategory::tryFrom((string) $this->category);
    }

    /** Waar dit doel over gaat, in gewone taal. */
    public function label(): string
    {
        return $this->custom_label ?? $this->reportCategory()?->label() ?? (string) $this->category;
    }

    /** Het doel in één zin: met streefcijfer als dat er is. */
    public function describe(): string
    {
        return $this->target_rating === null
            ? $this->label()
            : $this->label().' naar '.$this->target_rating;
    }
}
