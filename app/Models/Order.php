<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\OrderLineType;
use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\SplitAmount;
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

    /**
     * Het totaal per btw-tarief, in centen; de som is precies het totaal.
     *
     * Een korting staat als eigen regel zonder tarief, maar gaat over het
     * aanbod (zie OrderBuilder). Hij wordt dus naar rato over de tarieven van
     * het aanbod verdeeld, niet over inschrijfgeld of kleding.
     *
     * @return array<int, int> tarief => centen, zonder tarieven van nul euro
     */
    public function amountsPerVatRate(): array
    {
        $regels = $this->lines()->get();
        $korting = (int) $regels->where('type', OrderLineType::Discount)->sum('amount_cents');
        $perTarief = [];
        $basis = [];

        foreach ($regels as $regel) {
            if ($regel->type === OrderLineType::Discount) {
                continue;
            }

            $tarief = (int) $regel->vat_rate;
            $perTarief[$tarief] = ($perTarief[$tarief] ?? 0) + (int) $regel->amount_cents;

            if (self::isAanbodregel($regel)) {
                $basis[$tarief] = ($basis[$tarief] ?? 0) + (int) $regel->amount_cents;
            }
        }

        if ($korting !== 0 && $perTarief !== []) {
            foreach (SplitAmount::proportional($korting, $basis ?: $perTarief) as $tarief => $deel) {
                $perTarief[$tarief] += $deel;
            }
        }

        ksort($perTarief);

        return array_filter($perTarief, fn (int $c) => $c !== 0);
    }

    /**
     * Wat deze inschrijving netto op de order kost: zijn aanbodregels min zijn
     * deel van de korting, naar rato van het aanbod.
     */
    public function netCentsFor(Enrollment $enrollment): int
    {
        $regels = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $aanbod = $regels->filter(fn (OrderLine $r) => self::isAanbodregel($r));
        $eigen = (int) $aanbod->filter(fn (OrderLine $r) => (int) $r->enrollment_id === $enrollment->id)->sum('amount_cents');
        $totaal = (int) $aanbod->sum('amount_cents');
        $korting = (int) abs($regels->filter(fn (OrderLine $r) => $r->type === OrderLineType::Discount)->sum('amount_cents'));

        if ($eigen <= 0) {
            return 0;
        }

        $deel = $totaal > 0 ? intdiv($korting * $eigen, $totaal) : 0;

        return max(0, $eigen - $deel);
    }

    /**
     * Waarover het restitutiebeleid rekent bij het annuleren van dit kind: zijn
     * netto deel, maar nooit meer dan wat er op de order betaald is min wat er
     * voor andere kinderen al is teruggegeven. Zo komt er samen nooit meer
     * terug dan er binnenkwam.
     */
    public function refundableCentsFor(Enrollment $enrollment): int
    {
        $betalingen = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $inschrijvingen = $this->relationLoaded('enrollments') ? $this->enrollments : $this->enrollments()->get();

        $betaald = (int) $betalingen->filter(fn (Payment $p) => $p->status->countsAsRevenue())->sum('amount_cents');
        $alTerug = (int) $inschrijvingen
            ->filter(fn (Enrollment $e) => $e->id !== $enrollment->id && $e->status === EnrollmentStatus::Cancelled)
            ->sum('refund_cents');

        return max(0, min($this->netCentsFor($enrollment), $betaald - $alTerug));
    }

    protected static function isAanbodregel(OrderLine $regel): bool
    {
        return in_array($regel->type, [OrderLineType::Offering, OrderLineType::Trial], strict: true);
    }
}
