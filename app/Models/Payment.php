<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'player_id',
        'subscription_id',
        'purchase_id',
        'amount_cents',
        'vat_rate',
        'status',
        'method',
        'description',
        'due_on',
        'paid_at',
        'external_reference',
        'period_start',
        'installment_number',
        'installment_total',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'vat_rate' => 'integer',
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'due_on' => 'date',
            'period_start' => 'date',
            'paid_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** De order waar deze rekening uit voortkomt, als hij via een inschrijving ontstond. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** De eenmalige aankoop waar deze rekening bij hoort, als die er is. */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Paid->value);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PaymentStatus::Open->value,
            PaymentStatus::Failed->value,
            PaymentStatus::ChargedBack->value,
        ]);
    }

    /**
     * Te laat is: de vervaldag is voorbij.
     *
     * Op de dag zelf ben je niet te laat — `isPast()` zei van wel, want een
     * datumkolom staat op middernacht. Dat gaf twee waarheden: het tabblad
     * "Te laat" (PaymentQuery) telde de dag zelf niet mee, deze methode wel.
     */
    public function isOverdue(): bool
    {
        return $this->status === PaymentStatus::Open && $this->due_on->lt(today());
    }
}
