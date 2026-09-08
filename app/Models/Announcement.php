<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een mededeling aan ouders en spelers.
 *
 * Zonder groep gaat hij naar de hele school; met groep alleen naar de gezinnen
 * van die groep. Wie hem gekregen heeft staat vast in recipients_count.
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'author_id',
        'group_id',
        'training_id',
        'title',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'recipients_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function isForWholeSchool(): bool
    {
        return $this->group_id === null;
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
