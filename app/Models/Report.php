<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'player_id',
        'trainer_id',
        'reported_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'reported_on' => 'date',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ReportScore::class);
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('reported_on')->orderByDesc('id');
    }

    /** @return array<string, float> categorie => cijfer, met een decimaal */
    public function scoresByCategory(): array
    {
        return $this->scores
            ->mapWithKeys(fn (ReportScore $score) => [$score->category->value => $score->score])
            ->all();
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
}
