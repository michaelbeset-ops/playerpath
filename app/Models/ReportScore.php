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
            'score' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
