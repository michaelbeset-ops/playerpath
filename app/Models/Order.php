<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De financiële kop van een inschrijving: wat een ouder in één keer afrekent.
 *
 * Eén order kan meerdere kinderen bundelen (twee inschrijvingen, één
 * betaling) en draagt de regels: aanbod, inschrijfgeld, kledingpakket en
 * korting. Het totaal is de som van de regels; korting is een regel met een
 * negatief bedrag, zodat je later nog ziet waarom het lager was.
 */
class Order extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'total_cents',
        'discount_cents',
        'discount_code',
        'note',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_cents' => 'integer',
            'discount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** De ouder die betaalt. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Het totaal opnieuw uit de regels halen; de enige plek die het zet. */
    public function recalculate(): static
    {
        $regels = $this->lines()->get();

        $this->total_cents = (int) $regels->sum('amount_cents');
        $this->discount_cents = (int) abs($regels->where('amount_cents', '<', 0)->sum('amount_cents'));
        $this->save();

        return $this;
    }
}
