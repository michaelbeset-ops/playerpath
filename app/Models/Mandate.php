<?php

namespace App\Models;

use App\Enums\MandateStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een incassomachtiging, per ouder: die betaalt en tekent.
 *
 * Er staat alleen wat de betaalprovider ons teruggeeft: een klantkenmerk en
 * een mandaatkenmerk. **Nooit een IBAN.** Of het mandaat nog geldig is vragen
 * we bij elke incasso opnieuw aan de provider; een bank of ouder kan het
 * intrekken zonder dat wij het merken.
 */
class Mandate extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'customer_reference',
        'mandate_reference',
        'method',
        'status',
        'valid_from',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MandateStatus::class,
            'valid_from' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->status === MandateStatus::Valid && $this->revoked_at === null;
    }
}
