<?php

namespace App\Models;

use App\Enums\ReportCategory;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ReportScoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportScore extends Model
{
    /** @use HasFactory<ReportScoreFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'report_id',
        'category',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
            // Eén decimaal: een trainer denkt in "een zeven, maar wel een
            // goeie", en dat is een 7,4.
            'score' => 'float',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
