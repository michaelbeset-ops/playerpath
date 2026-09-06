<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wat de platformbeheerder heeft gedaan.
 *
 * Bestaat omdat je bij een vraag van een school ("wie heeft onze kalender
 * uitgezet?") een antwoord wilt kunnen geven. Dat is precies het soort ding
 * dat je achteraf niet meer kunt reconstrueren als je het niet vastlegt.
 *
 * Geen school-scope: dit hoort bij het platform en wordt alleen in de
 * beheeromgeving gelezen. De naam van de school staat er als tekst bij, zodat
 * een regel leesbaar blijft nadat de school zelf verwijderd is.
 */
class PlatformLog extends Model
{
    protected $fillable = [
        'admin_id',
        'school_id',
        'admin_email',
        'school_name',
        'action',
        'summary',
        'details',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
