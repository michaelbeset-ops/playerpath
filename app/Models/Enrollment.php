<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlayerPosition;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'position',
        'guardian_name',
        'guardian_email',
        'guardian_phone',
        'relationship',
        'plan_id',
        'payment_method',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'position' => PlayerPosition::class,
            'payment_method' => PaymentMethod::class,
            'status' => EnrollmentStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Pending->value);
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
