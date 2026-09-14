<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Het begin- of eindniveau van een kind in een cursus of blok, per categorie
 * in kleuren. Alleen voor de inzetkaart; zie Support\Progress\CourseProgress.
 *
 * `levels` is categorie => positie op de schaal (0 is de laagste kleur),
 * `scale` het aantal niveaus dat de school toen had.
 */
class CourseAssessment extends Model
{
    use BelongsToSchool;

    public const BEGIN = 'begin';

    public const EIND = 'eind';

    protected $fillable = [
        'product_id',
        'player_id',
        'moment',
        'levels',
        'scale',
        'note',
        'assessed_by',
        'assessed_on',
    ];

    protected function casts(): array
    {
        return [
            'levels' => 'array',
            'scale' => 'integer',
            'assessed_on' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
