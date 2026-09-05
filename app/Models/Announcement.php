<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AnnouncementFactory;
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
}
