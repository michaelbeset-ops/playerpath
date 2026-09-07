<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\Registration;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'training_id',
        'player_id',
        'registration',
        'registered_by_id',
        'registration_note',
        'status',
    ];

    /**
     * Van welke rittenkaart de beurt is afgeschreven.
     *
     * Staat niet in $fillable: dat zet alleen Actions\Products\ConsumeCredit,
     * want daar wordt de beurt ook echt van de kaart gehaald. Zonder deze
     * verwijzing kun je een vinkje niet terugdraaien zonder te gokken van welke
     * kaart de beurt kwam.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    protected function casts(): array
    {
        return [
            'registration' => Registration::class,
            'status' => AttendanceStatus::class,
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

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_id');
    }
}
