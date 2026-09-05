<?php

namespace App\Models;

use App\Enums\GoalStatus;
use App\Enums\ReportCategory;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'player_id',
        'set_by_id',
        'category',
        'start_rating',
        'target_rating',
        'starts_on',
        'due_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
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
}
