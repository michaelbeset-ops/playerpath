<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasStatusMachine;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToSchool, HasFactory, HasStatusMachine;

    protected $fillable = [
        'player_id',
        'product_id',
        'payment_option_id',
        'enrollment_id',
        'amount_cents',
        'vat_rate',
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
            'vat_rate' => 'integer',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** De betaalvorm waaruit dit abonnement ontstond; het bedrag hier is leidend. */
    public function paymentOption(): BelongsTo
    {
        return $this->belongsTo(PaymentOption::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Alles wat nog rekeningen voortbrengt: ook met een geplande opzegging loopt het door. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (SubscriptionStatus $s) => $s->value,
            array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $s) => $s->bills()),
        ));
    }

    /** Wat dit abonnement per jaar oplevert. Eenmalig telt niet mee. */
    public function yearlyValueCents(): int
    {
        return $this->amount_cents * $this->interval->timesPerYear();
    }
}
