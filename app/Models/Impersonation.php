<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een vastgelegde keer dat de platformbeheerder als iemand anders keek.
 *
 * Bewust een tabel en geen logregel: bij een vraag van een school over wie er
 * in hun gegevens heeft gekeken wil je een antwoord kunnen geven, en dat moet
 * je niet uit tekstbestanden hoeven vissen.
 *
 * Dit model heeft géén school-scope. Het hoort bij het platform, niet bij een
 * school, en wordt alleen in de beheeromgeving gelezen.
 */
class Impersonation extends Model
{
    protected $fillable = [
        'admin_id',
        'user_id',
        'school_id',
        'admin_email',
        'user_email',
        'ip_address',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
