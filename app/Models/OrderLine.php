<?php

namespace App\Models;

use App\Enums\OrderLineType;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén regel op een order. Naam en bedrag zijn overgenomen op het moment van
 * bestellen, net als bij een aankoop: een prijswijziging raakt een gedane
 * afspraak niet.
 */
class OrderLine extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'order_id',
        'type',
        'description',
        'player_id',
        'product_id',
        'enrollment_id',
        'discount_id',
        'quantity',
        'amount_cents',
        'vat_rate',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderLineType::class,
            'quantity' => 'integer',
            'amount_cents' => 'integer',
            'vat_rate' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
