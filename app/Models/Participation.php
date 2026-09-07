<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een speler die meedoet aan een aanbod.
 *
 * Bewust naast `group_player`: die zegt alleen "zit in deze groep". Hier hoort
 * een status bij — ingeschreven, wachtlijst, geannuleerd — en de rekening die
 * eraan hangt. Zonder dat onderscheid kun je geen wachtlijst bijhouden en weet
 * je later niet meer waarvoor iemand betaald heeft.
 */
class Participation extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'product_id',
        'player_id',
        'status',
        'purchase_id',
        'subscription_id',
        'enrollment_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
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

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', ParticipationStatus::Confirmed->value);
    }

    /** De wachtlijst op volgorde van aanmelden. */
    public function scopeWaitlist(Builder $query): Builder
    {
        return $query->where('status', ParticipationStatus::Waitlist->value)->orderBy('created_at');
    }
}
