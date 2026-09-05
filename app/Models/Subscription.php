<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'player_id',
        'plan_id',
        'amount_cents',
        'interval',
        'installments',
        'status',
        'payment_method',
        'starts_on',
        'ends_on',
        'external_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'interval' => BillingInterval::class,
            'installments' => 'integer',
            'status' => SubscriptionStatus::class,
            'payment_method' => PaymentMethod::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active->value);
    }

    /** Wat dit abonnement per jaar oplevert. Eenmalig telt niet mee. */
    public function yearlyValueCents(): int
    {
        return $this->amount_cents * $this->interval->timesPerYear();
    }
}
