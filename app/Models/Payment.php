<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasStatusMachine;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToSchool, HasFactory, HasStatusMachine;

    protected $fillable = [
        'player_id',
        'subscription_id',
        'purchase_id',
        'order_id',
        'training_enrollment_id',
        'parent_id',
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
            'prenotified_at' => 'datetime',
            'reminder_count' => 'integer',
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
     * Op de dag zelf ben je niet te laat - `isPast()` zei van wel, want een
     * datumkolom staat op middernacht. Dat gaf twee waarheden: het tabblad
     * "Te laat" (PaymentQuery) telde de dag zelf niet mee, deze methode wel.
     */
    /** De ouder die betaalt: via de order, anders de eerste ouder van het kind. */
    public function payer(): ?User
    {
        return $this->order?->user ?? $this->player?->guardians()->first();
    }

    /**
     * Tot wanneer een ouder deze incasso kan terugdraaien: acht weken na de
     * afschrijving. Alleen bij incasso; iDEAL is definitief.
     */
    public function chargebackWindowClosesAt(): ?CarbonInterface
    {
        if ($this->method !== PaymentMethod::DirectDebit || $this->paid_at === null) {
            return null;
        }

        return $this->paid_at->copy()->addWeeks(8);
    }

    public function isWithinChargebackWindow(): bool
    {
        $sluit = $this->chargebackWindowClosesAt();

        return $sluit !== null && $sluit->isFuture();
    }

    public function isOverdue(): bool
    {
        return $this->status === PaymentStatus::Open && $this->due_on->lt(today()) && ! $this->isCashAtTraining();
    }

    /** De losse trainingsaanmelding waar deze rekening bij hoort, als die er is. */
    public function trainingEnrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class);
    }

    /**
     * Contant af te rekenen bij de training.
     *
     * Dat is geen achterstand en geen reden voor een aanmaning: de afspraak
     * is dat het geld bij de training wordt gegeven, en de trainer vinkt dat
     * daar af. Het staat bij de school als "nog te ontvangen".
     */
    public function isCashAtTraining(): bool
    {
        return $this->training_enrollment_id !== null && $this->method === PaymentMethod::Cash;
    }
}
