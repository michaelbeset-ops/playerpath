<?php

namespace App\Models;

use App\Enums\PaymentOptionType;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén manier om een aanbod te betalen: eenmalig, in termijnen of als abonnement.
 *
 * Meerdere per aanbod, precies één is de standaard. Wat er is afgesproken
 * neemt het bedrag over (inschrijving, abonnement, aankoop); een betaalvorm
 * later aanpassen raakt een lopende afspraak dus niet.
 */
class PaymentOption extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'label',
        'amount_cents',
        'installments',
        'interval',
        'is_default',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentOptionType::class,
            'amount_cents' => 'integer',
            'installments' => 'integer',
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** "€ 120,00 ineens", "3 × € 40,00 per maand", "€ 30,00 per maand". */
    public function describe(): string
    {
        $bedrag = Money::format($this->amount_cents);

        return match ($this->type) {
            PaymentOptionType::Eenmalig => $this->amount_cents === 0 ? 'Gratis' : "{$bedrag} ineens",
            PaymentOptionType::Termijnen => "{$this->installments} × {$bedrag}".($this->interval === 'week' ? ' per week' : ' per maand'),
            PaymentOptionType::Abonnement => $bedrag.' '.match ($this->interval) {
                'quarterly' => 'per kwartaal',
                'yearly' => 'per jaar',
                default => 'per maand',
            },
        };
    }

    /** Het totaal over de hele looptijd, voor zover dat vaststaat. */
    public function totalCents(): ?int
    {
        return match ($this->type) {
            PaymentOptionType::Eenmalig => $this->amount_cents,
            PaymentOptionType::Termijnen => $this->amount_cents * (int) $this->installments,
            PaymentOptionType::Abonnement => null,
        };
    }
}
