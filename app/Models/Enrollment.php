<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlayerPosition;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasStatusMachine;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use BelongsToSchool, HasFactory, HasStatusMachine;

    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'position',
        'guardian_name',
        'guardian_email',
        'guardian_phone',
        'relationship',
        'product_id',
        'payment_option_id',
        'order_id',
        'guardian_user_id',
        'payment_method',
        'waitlist',
        'note',
        'details',
    ];

    /**
     * Een nieuwe inschrijving begint als concept. De kolom zelf heeft nog de
     * oude standaard 'pending', en die bestaat in de statusmachine niet meer:
     * zonder deze regel stond een net aangemaakte inschrijving tot haar eerste
     * statuswissel op een waarde die niemand kan lezen of tellen.
     */
    protected $attributes = [
        'status' => 'concept',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'position' => PlayerPosition::class,
            'payment_method' => PaymentMethod::class,
            'status' => EnrollmentStatus::class,
            'waitlist' => 'boolean',
            'handled_at' => 'datetime',
            'details' => 'array',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refund_cents' => 'integer',
            'renewal_invited_at' => 'datetime',
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

    /** De gekozen betaalvorm; het bedrag zelf staat op de order en de afspraak. */
    public function paymentOption(): BelongsTo
    {
        return $this->belongsTo(PaymentOption::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** De ouder met een account, zodra die er is. */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    /** Wat nog bij de school ligt om goed te keuren. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::AwaitingApproval->value);
    }

    /** Alles wat nog niet rond of afgesloten is. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (EnrollmentStatus $s) => $s->value,
            array_filter(EnrollmentStatus::cases(), fn (EnrollmentStatus $s) => $s->isOpen()),
        ));
    }

    /** Wat een plek in het aanbod vasthoudt zonder al bevestigd te zijn. Zie EnrollmentStatus::holdsSpot(). */
    public function scopeHoldingSpot(Builder $query): Builder
    {
        return $query->whereIn('status', EnrollmentStatus::holdingSpot());
    }

    public function getChildNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
