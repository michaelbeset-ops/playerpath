<?php

namespace App\Models;

use App\Enums\TrainingEnrollmentStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Een losse aanmelding van een kind op één training.
 *
 * Naast de groep, niet in plaats daarvan: wie in de groep zit is er gewoon,
 * wie hier staat is er voor deze ene keer. De rekening is een gewone Payment
 * die naar deze aanmelding wijst.
 */
class TrainingEnrollment extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'training_id',
        'player_id',
        'user_id',
        'status',
        'payment_method',
        'note',
        'invited_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrainingEnrollmentStatus::class,
            'invited_at' => 'datetime',
        ];
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** De ouder die aanmeldde. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TrainingEnrollmentStatus::activeValues());
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', TrainingEnrollmentStatus::Confirmed->value);
    }

    public function scopeRequested(Builder $query): Builder
    {
        return $query->where('status', TrainingEnrollmentStatus::Requested->value);
    }

    public function scopeWaitlisted(Builder $query): Builder
    {
        return $query->where('status', TrainingEnrollmentStatus::Waitlisted->value)->orderBy('created_at');
    }

    /** Betaalt dit gezin contant bij de training? */
    public function paysCash(): bool
    {
        return $this->payment_method === 'cash';
    }
}
