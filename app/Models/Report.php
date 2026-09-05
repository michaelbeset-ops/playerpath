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

    /** @return array<string, int> categorie => cijfer */
    public function scoresByCategory(): array
    {
        return $this->scores
            ->mapWithKeys(fn (ReportScore $score) => [$score->category->value => $score->score])
            ->all();
    }
}
